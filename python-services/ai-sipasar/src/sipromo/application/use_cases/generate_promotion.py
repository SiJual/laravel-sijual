"""GeneratePromotionUseCase - orchestrates brief validation, RAG, tool calling,
structured output, grounding validation, and atomic persistence."""

from __future__ import annotations

import asyncio
import json
import logging
import time
import uuid
from dataclasses import dataclass, field
from datetime import UTC, datetime
from typing import Any
from urllib.parse import urlparse
from uuid import UUID

import httpx
from pydantic import ValidationError

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    GeneratePromotionCommand,
)
from sipromo.application.dto.promotion_responses import PromotionDraftDTO
from sipromo.application.policies.content_safety import (
    ContentPolicyPort,
    ContentValidationContext,
)
from sipromo.application.policies.tool_policy import ToolPolicy
from sipromo.application.ports.embeddings import EmbeddingPort
from sipromo.application.ports.llm import (
    ContextBlock,
    LLMPort,
    LLMRequest,
    ToolResultForModel,
)
from sipromo.application.ports.object_storage import ObjectStoragePort, UploadAsset
from sipromo.application.ports.poster_generator import (
    PosterGeneratorPort,
    PosterSpec,
    ProductMedia,
)
from sipromo.application.ports.repositories import (
    BusinessReadRepository,
    ContentReadRepository,
    ContentWriteRepository,
    KnowledgeReadRepository,
    RunRepository,
)
from sipromo.application.ports.retriever import (
    RetrievalQuery,
    RetrievedChunk,
    RetrieverPort,
)
from sipromo.application.ports.tool_executor import (
    ExecutionContext,
    ToolExecutorPort,
    ToolRegistryPort,
)
from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.application.use_cases.prompts import (
    PROMPT_VERSION,
    build_llm_request,
    build_system_instruction,
    sanitize_block,
)
from sipromo.domain.entities.promotion_content import PromotionContent, PromotionOutput
from sipromo.domain.entities.source_evidence import SourceEvidence
from sipromo.domain.exceptions import (
    ClaimViolationError,
    InvalidModelResponse,
    ProductNotFoundError,
    ToolBudgetExceeded,
    UnauthorizedActionError,
)
from sipromo.domain.value_objects.content_type import DocumentType, SourceKind
from sipromo.infrastructure.tools.read_tools import (
    inventory_eligibility_for_model,
    product_facts_for_model,
)
from sipromo.infrastructure.tools.registry import _dereference

logger = logging.getLogger(__name__)


@dataclass
class OrchestrationState:
    """Accumulated state across the tool loop, used for audit and validation."""

    executed_tool_calls: list[Any] = field(default_factory=list)
    tool_results: list[ToolResultForModel] = field(default_factory=list)
    context_blocks: list[ContextBlock] = field(default_factory=list)
    usage: dict[str, Any] = field(default_factory=dict)
    validation_metadata: dict[str, Any] = field(default_factory=dict)
    retrieved_chunks: list[Any] = field(default_factory=list)
    used_rag: bool = False

    def record(self, tool_result: Any) -> None:
        self.executed_tool_calls.append(tool_result)
        self.tool_results.append(tool_result.for_model())


class GeneratePromotionUseCase:
    def __init__(
        self,
        *,
        unit_of_work: UnitOfWorkPort,
        retriever: RetrieverPort,
        embeddings: EmbeddingPort,
        llm: LLMPort,
        tool_executor: ToolExecutorPort,
        tool_registry: ToolRegistryPort,
        business_repo: BusinessReadRepository,
        knowledge_repo: KnowledgeReadRepository,
        content_read: ContentReadRepository,
        content_write: ContentWriteRepository,
        run_repo: RunRepository,
        content_policy: ContentPolicyPort,
        ai_max_tool_rounds: int,
        ai_max_tool_calls: int,
        llm_run_max_total_tokens: int = 60_000,
        prompt_version: str = PROMPT_VERSION,
        poster_generator: PosterGeneratorPort | None = None,
        storage: ObjectStoragePort | None = None,
    ) -> None:
        self._unit_of_work = unit_of_work
        self._retriever = retriever
        self._embeddings = embeddings
        self._llm = llm
        self._tool_executor = tool_executor
        self._tool_registry = tool_registry
        self._business_repo = business_repo
        self._knowledge_repo = knowledge_repo
        self._content_read = content_read
        self._content_write = content_write
        self._run_repo = run_repo
        self._content_policy = content_policy
        self._ai_max_tool_rounds = ai_max_tool_rounds
        self._ai_max_tool_calls = ai_max_tool_calls
        self._llm_run_max_total_tokens = llm_run_max_total_tokens
        self._prompt_version = prompt_version
        self._poster_generator = poster_generator
        self._storage = storage

    # ------------------------------------------------------------------ #
    # Public entry point
    # ------------------------------------------------------------------ #

    async def execute(
        self, command: GeneratePromotionCommand, actor: AuthenticatedActor
    ) -> PromotionDraftDTO:
        if actor.role not in {"owner", "staff"}:
            raise UnauthorizedActionError("Only owner or staff can generate promotions")

        run_id = uuid.uuid4()
        brief = command.brief
        umkm_id = actor.umkm_id

        # 1. Validate product ownership (deterministic, before any AI call).
        products = await self._business_repo.get_products(umkm_id, brief.product_ids)
        found = {p.product_id for p in products}
        missing = [str(pid) for pid in brief.product_ids if pid not in found]
        if missing:
            raise ProductNotFoundError()

        # 2. Open a generation run.
        async with self._unit_of_work.begin():
            await self._run_repo.create_run(
                run_id=run_id,
                umkm_id=umkm_id,
                user_id=actor.user_id,
                request_id=command.request_id,
                model_provider=self._llm.provider_name,
                model_name=self._llm.model_name,
                prompt_version=self._prompt_version,
                brief=brief.model_dump(mode="json"),
            )

        started_at = time.monotonic()
        state = OrchestrationState()
        try:
            output = await self._run_agent_loop(brief, umkm_id, actor, run_id, state)
            if not state.used_rag:
                output.warnings.append(
                    "Brand knowledge tidak ditemukan - hasil disusun dari "
                    "data bisnis terstruktur saja."
                )

            # 3. Grounding + policy validation (deterministic second pass).
            validation_context = await self._build_validation_context(
                umkm_id, brief, products, state
            )
            self._drop_invalid_evidence(output, validation_context.evidence_ids)
            violations = self._content_policy.validate(output, validation_context)
            if violations:
                state.validation_metadata["violations_first_pass"] = violations
                repaired = await self._repair_once(
                    brief, umkm_id, actor, run_id, state, output, violations
                )
                if repaired is None:
                    await self._fail_run(run_id, "CLAIM_VIOLATION")
                    raise ClaimViolationError(violations)
                output = repaired
                self._drop_invalid_evidence(output, validation_context.evidence_ids)
                violations = self._content_policy.validate(output, validation_context)
                state.validation_metadata["violations_after_repair"] = violations
                if violations:
                    await self._fail_run(run_id, "CLAIM_VIOLATION")
                    raise ClaimViolationError(violations)
            warnings = list(output.warnings)

            # 4. Atomic persistence.
            content_id = uuid.uuid4()
            image_url = await self._make_poster(content_id, umkm_id, brief, output, products)
            await self._persist(
                content_id=content_id,
                run_id=run_id,
                umkm_id=umkm_id,
                actor=actor,
                brief=brief,
                output=output,
                state=state,
                warnings=warnings,
                image_url=image_url,
                valid_evidence_ids=validation_context.evidence_ids,
            )
            state.validation_metadata["passed"] = True
            await self._complete_run(
                run_id, content_id, state, retrieved_count=len(state.retrieved_chunks)
            )
            logger.info(
                "promotion_generated",
                extra={
                    "run_id": str(run_id),
                    "content_id": str(content_id),
                    "duration_ms": int((time.monotonic() - started_at) * 1000),
                    "tool_calls": len(state.executed_tool_calls),
                },
            )
            return PromotionDraftDTO(
                content_id=content_id,
                generation_run_id=run_id,
                status="draft",
                version=1,
                title=output.title,
                primary_copy=output.primary_copy,
                caption=output.caption,
                hashtags=output.hashtags,
                call_to_action=output.call_to_action,
                visual_brief=output.visual_brief,
                target_audience_summary=output.target_audience_summary,
                rationale=output.rationale,
                claims=output.claims,
                evidence=[
                    e for e in output.evidence if e.evidence_id in validation_context.evidence_ids
                ],
                warnings=warnings,
                requires_human_review=output.requires_human_review,
                image_url=image_url,
                created_at=datetime.now(UTC),
            )
        except Exception:
            await self._fail_run_quiet(run_id)
            raise

    # ------------------------------------------------------------------ #
    # Agent loop (section 26 of blueprint)
    # ------------------------------------------------------------------ #

    async def _run_agent_loop(
        self,
        brief: Any,
        umkm_id: UUID,
        actor: AuthenticatedActor,
        run_id: UUID,
        state: OrchestrationState,
    ) -> PromotionOutput:
        # Deterministic prefetch - facts queried by the app, not the model.
        prefetched = await self._prefetch_context(brief, umkm_id)
        state.retrieved_chunks = prefetched["chunks"]
        state.used_rag = bool(prefetched["chunks"])
        state.context_blocks = list(prefetched["blocks"])

        request = build_llm_request(
            brief=brief,
            context_blocks=state.context_blocks,
            tools=self._tool_registry.declarations(),
            temperature=None,
            max_output_tokens=None,
            json_schema=None,
            system_instruction=build_system_instruction(self._prompt_version),
        )
        request.context_blocks = state.context_blocks

        turn = await self._llm.generate_with_tools(request)
        state.usage.update(turn.usage or {})
        total_calls = 0

        for _round_index in range(self._ai_max_tool_rounds):
            if state.usage.get("total_tokens", 0) > self._llm_run_max_total_tokens:
                logger.warning(
                    "llm token budget exhausted, forcing final answer",
                    extra={"total_tokens": state.usage.get("total_tokens", 0)},
                )
                return await self._request_structured_output(brief, state)

            if turn.final_output is not None:
                try:
                    return _sanitize_output(PromotionOutput.model_validate(turn.final_output))
                except ValidationError as exc:
                    return await self._repair_invalid_structured(turn.final_output, request, exc)

            if not turn.tool_calls:
                # Model stopped with free text - force a schema-enforced final answer.
                return await self._request_structured_output(brief, state)

            round_results: list[ToolResultForModel] = []
            for proposed in turn.tool_calls:
                total_calls += 1
                if total_calls > self._ai_max_tool_calls:
                    raise ToolBudgetExceeded(self._ai_max_tool_calls)

                validated = self._tool_registry.validate(proposed)
                ToolPolicy.authorize(validated.tool_name, actor.role)
                context = ExecutionContext(
                    umkm_id=umkm_id,
                    user_id=actor.user_id,
                    role=actor.role,
                    request_id=str(run_id),
                    generation_run_id=run_id,
                )
                result = await self._tool_executor.execute(validated, context)
                state.record(result)
                round_results.append(ToolResultForModel(**result.for_model()))
                await self._record_tool_call(run_id, validated, result)

            state.context_blocks.extend(
                ContextBlock(
                    block_id=r.call_id,
                    kind="tool_result",
                    content=json.dumps(r, ensure_ascii=False, default=str),
                )
                for r in round_results
            )
            request.context_blocks = state.context_blocks
            turn = await self._llm.continue_with_tool_results(request, round_results)
            state.usage.update(turn.usage or {})

        # Tool budget exhausted - the agent may still produce a grounded draft.
        return await self._request_structured_output(brief, state)

    async def _request_structured_output(
        self, brief: Any, state: OrchestrationState
    ) -> PromotionOutput:
        """Schema-enforced final answer with all accumulated context, no tools."""
        context_blocks = list(state.context_blocks)
        evidence_ids = sorted(self._collect_evidence_ids(state))
        context_blocks.append(
            ContextBlock(
                block_id="available_evidence_ids",
                kind="rag_chunk",
                content=(
                    "Daftar evidence_id yang valid. Gunakan persis id berikut pada field "
                    "evidence (jangan membuat id baru):\n" + json.dumps(evidence_ids)
                ),
            )
        )
        final_request = build_llm_request(
            brief=brief,
            context_blocks=context_blocks,
            tools=[],
            temperature=None,
            max_output_tokens=None,
            json_schema=_promotion_output_schema(),
            system_instruction=build_system_instruction(self._prompt_version),
        )
        turn = await self._llm.generate_with_tools(final_request)
        state.usage.update(turn.usage or {})
        if turn.final_output is None:
            raise InvalidModelResponse()
        try:
            return _sanitize_output(PromotionOutput.model_validate(turn.final_output))
        except ValidationError as exc:
            return await self._repair_invalid_structured(turn.final_output, final_request, exc)

    # ------------------------------------------------------------------ #
    # Prefetch
    # ------------------------------------------------------------------ #

    async def _prefetch_context(self, brief: Any, umkm_id: UUID) -> dict[str, Any]:
        blocks: list[ContextBlock] = []
        chunks: list[Any] = []

        profile = await self._business_repo.get_business_profile(umkm_id)
        if profile:
            blocks.append(
                ContextBlock(
                    block_id="prefetch:get_business_profile",
                    kind="tool_result",
                    content=json.dumps(profile.model_dump(mode="json"), ensure_ascii=False),
                )
            )

        products = await self._business_repo.get_products(umkm_id, brief.product_ids)
        if products:
            blocks.append(
                ContextBlock(
                    block_id="prefetch:get_products",
                    kind="tool_result",
                    content=json.dumps(product_facts_for_model(products), ensure_ascii=False),
                )
            )

        inventory = await self._business_repo.get_inventory_eligibility(umkm_id, brief.product_ids)
        if inventory:
            blocks.append(
                ContextBlock(
                    block_id="prefetch:get_inventory_eligibility",
                    kind="tool_result",
                    content=json.dumps(
                        inventory_eligibility_for_model(inventory), ensure_ascii=False
                    ),
                )
            )

        if brief.include_market_context:
            market = await self._business_repo.get_latest_market_summary(umkm_id)
            if market:
                blocks.append(
                    ContextBlock(
                        block_id="prefetch:get_market_summary",
                        kind="tool_result",
                        content=json.dumps(market.model_dump(mode="json"), ensure_ascii=False),
                    )
                )

        if brief.include_business_performance:
            from datetime import date, timedelta

            end = date.today()
            start = end - timedelta(days=90)
            sales = await self._business_repo.get_sales_summary(
                umkm_id, brief.product_ids, start, end
            )
            if sales:
                blocks.append(
                    ContextBlock(
                        block_id="prefetch:get_sales_summary",
                        kind="tool_result",
                        content=json.dumps(
                            [s.model_dump(mode="json") for s in sales], ensure_ascii=False
                        ),
                    )
                )

        # Hybrid retrieval over narrative knowledge.
        query_text = " ".join(
            filter(None, [brief.key_message, brief.target_audience, *brief.constraints])
        )
        query = RetrievalQuery(
            query=sanitize_block(query_text, 2000),
            umkm_id=str(umkm_id),
            top_k_vector=12,
            top_k_lexical=12,
            final_k=8,
            min_score=0.55,
            max_context_tokens=6000,
        )
        chunks = await self._retriever.retrieve(query)
        for chunk in chunks:
            blocks.append(
                ContextBlock(
                    block_id=chunk.chunk_id,
                    kind="rag_chunk",
                    content=chunk.content,
                )
            )

        # Brand voice and campaign style are global context: always include
        # them so the copy follows the brand guide even when the query does
        # not lexically overlap with brand documents.
        brand_chunks = await self._knowledge_repo.list_chunks_by_document_type(
            umkm_id=umkm_id,
            document_types=[DocumentType.BRAND_GUIDE, DocumentType.CAMPAIGN_EXAMPLE],
            limit=8,
        )
        known_ids = {c.chunk_id for c in chunks}
        for brand in brand_chunks:
            if brand.chunk_id in known_ids:
                continue
            chunks.append(
                RetrievedChunk(
                    chunk_id=str(brand.chunk_id),
                    document_id=str(brand.document_id),
                    umkm_id=str(umkm_id),
                    document_type=brand.document_type,
                    content=brand.content[:1200],
                    metadata=brand.metadata,
                    score=brand.score,
                )
            )
        for retrieved in chunks:
            if retrieved.chunk_id not in known_ids:
                blocks.append(
                    ContextBlock(
                        block_id=retrieved.chunk_id,
                        kind="rag_chunk",
                        content=retrieved.content,
                    )
                )

        return {"blocks": blocks, "chunks": chunks}

    # ------------------------------------------------------------------ #
    # Grounding context
    # ------------------------------------------------------------------ #

    async def _build_validation_context(
        self,
        umkm_id: UUID,
        brief: Any,
        products: list[Any],
        state: OrchestrationState,
    ) -> ContentValidationContext:
        available_claims: set[str] = set()
        inventory_eligible: dict[str, bool] = {}
        product_names: set[str] = set()
        certifications: set[str] = set()

        profile = await self._business_repo.get_business_profile(umkm_id)
        if profile:
            meta = profile.brand_metadata or {}
            certifications.update(meta.get("certifications", []))
            available_claims.add(profile.name.strip().lower())
            if profile.city:
                available_claims.add(profile.city.strip().lower())
            if profile.province:
                available_claims.add(profile.province.strip().lower())
            if profile.city and profile.province:
                available_claims.add(
                    f"{profile.city.strip().lower()} {profile.province.strip().lower()}"
                )
            if profile.business_type:
                available_claims.add(profile.business_type.strip().lower())

        for product in products:
            product_names.add(product.name)
            available_claims.add(product.name.strip().lower())
            if product.price is not None:
                available_claims.add(f"rp{product.price}")
                available_claims.add(f"idr{product.price}")
            if product.stock_level is not None:
                available_claims.add(f"stock:{product.stock_level}")
            if product.description:
                available_claims.add(product.description.strip().lower())

        for inv in await self._business_repo.get_inventory_eligibility(umkm_id, brief.product_ids):
            inventory_eligible[str(inv.product_id)] = inv.eligible
        if inventory_eligible and any(inventory_eligible.values()):
            available_claims.update({"stok tersedia", "tersedia", "in_stock"})

        for exec_call in state.executed_tool_calls:
            if exec_call.status == "succeeded" and exec_call.tool_name == "get_sales_summary":
                data = exec_call.data or []
                for row in data:
                    if row.get("total_revenue") is not None:
                        available_claims.add(f"rp{int(row['total_revenue'])}")

        evidence_ids = self._collect_evidence_ids(state)

        competitor_terms = await self._business_repo.get_competitor_terms(umkm_id)

        return ContentValidationContext(
            available_tool_claims=available_claims,
            available_product_names=product_names,
            inventory_eligible=inventory_eligible,
            available_certifications=certifications,
            allowed_discounts=False,
            evidence_ids=evidence_ids,
            competitor_terms=competitor_terms,
        )

    @staticmethod
    def _collect_evidence_ids(state: OrchestrationState) -> set[str]:
        evidence_ids = {
            "prefetch:get_business_profile",
            "prefetch:get_products",
            "prefetch:get_inventory_eligibility",
            "prefetch:get_market_summary",
            "prefetch:get_sales_summary",
        } | {c.call_id for c in state.executed_tool_calls}
        for chunk in state.retrieved_chunks:
            evidence_ids.add(chunk.chunk_id)
        return evidence_ids

    @staticmethod
    def _drop_invalid_evidence(output: PromotionOutput, valid_evidence_ids: set[str]) -> None:
        """Models sometimes cite ids that were never provided (e.g. product
        uuids seen in tool results). Evidence is audit metadata - persistence
        skips unknown ids anyway - so drop them instead of failing the run."""
        kept = [e for e in output.evidence if e.evidence_id in valid_evidence_ids]
        dropped = len(output.evidence) - len(kept)
        if dropped:
            output.evidence = kept
            output.warnings.append(f"evidence id tidak tersedia dan dihilangkan: {dropped}")

    # ------------------------------------------------------------------ #
    # Repair paths (section 13.1)
    # ------------------------------------------------------------------ #

    async def _repair_invalid_structured(
        self, invalid_raw: dict, request: LLMRequest, exc: ValidationError
    ) -> PromotionOutput:
        errors = [f"{'.'.join(map(str, e['loc']))}: {e['msg']}" for e in exc.errors()]
        if request.json_schema is None:
            request = request.model_copy(update={"json_schema": _promotion_output_schema()})
        repaired = await self._llm.repair_output(request, invalid_raw, errors)
        if repaired is None:
            raise InvalidModelResponse("Structured output invalid after repair attempt")
        return _sanitize_output(PromotionOutput.model_validate(repaired))

    async def _repair_once(
        self,
        brief: Any,
        umkm_id: UUID,
        actor: AuthenticatedActor,
        run_id: UUID,
        state: OrchestrationState,
        output: PromotionOutput,
        violations: list[str],
    ) -> PromotionOutput | None:
        request = build_llm_request(
            brief=brief,
            context_blocks=state.context_blocks,
            tools=self._tool_registry.declarations(),
            temperature=None,
            max_output_tokens=None,
            json_schema=None,
            system_instruction=(
                build_system_instruction(self._prompt_version)
                + "\n\nValidasi grounding gagal. Perbaiki output dengan menghilangkan "
                + "klaim yang tidak didukung dan tautkan evidence yang valid. "
                + "Daftar violation:\n- "
                + "\n- ".join(violations)
            ),
        )
        turn = await self._llm.generate_with_tools(request)
        if turn.final_output is None:
            return None
        try:
            return _sanitize_output(PromotionOutput.model_validate(turn.final_output))
        except ValidationError:
            return None

    # ------------------------------------------------------------------ #
    # Persistence
    # ------------------------------------------------------------------ #

    async def _make_poster(
        self,
        content_id: UUID,
        umkm_id: UUID,
        brief: Any,
        output: PromotionOutput,
        products: list[Any],
    ) -> str | None:
        """Render the poster and upload it; a failure never fails the run."""
        if self._poster_generator is None:
            return None
        brand_name = "SiPromo"
        profile = await self._business_repo.get_business_profile(umkm_id)
        if profile:
            brand_name = profile.name
        product_media = []
        for p in products:
            if not getattr(p, "name", None):
                continue
            product_media.append(
                ProductMedia(
                    name=p.name,
                    description=getattr(p, "description", None) or "",
                    image_bytes=await _product_image(p),
                )
            )
        spec = PosterSpec(
            brand_name=brand_name,
            headline=output.title,
            message=output.primary_copy,
            product_media=product_media,
            call_to_action=output.call_to_action,
            hashtags=output.hashtags,
            tone=brief.tone.value,
        )
        try:
            png = await asyncio.to_thread(self._poster_generator.generate, spec)
            if self._storage is None:
                logger.warning(
                    "poster generated but object storage unavailable",
                    extra={"content_id": str(content_id)},
                )
                return None
            stored = await self._storage.upload(
                UploadAsset(
                    file_bytes=png,
                    folder="promo-posters",
                    public_id=f"poster-{content_id}",
                    resource_type="image",
                )
            )
            return stored.secure_url[:255] or None
        except Exception as exc:
            logger.warning(
                "poster generation failed: %s", exc, extra={"content_id": str(content_id)}
            )
            return None

    async def _persist(
        self,
        *,
        content_id: UUID,
        run_id: UUID,
        umkm_id: UUID,
        actor: AuthenticatedActor,
        brief: Any,
        output: PromotionOutput,
        state: OrchestrationState,
        warnings: list[str],
        image_url: str | None,
        valid_evidence_ids: set[str],
    ) -> None:
        async with self._unit_of_work.begin():
            content = PromotionContent.from_output(
                content_id=str(content_id),
                umkm_id=str(umkm_id),
                content_type=brief.content_type.value,
                output=output,
            )
            brand_metadata = {
                "schema_version": "1.0",
                "platform": brief.platform.value,
                "objective": brief.objective.value,
                "visual_brief": output.visual_brief,
                "target_audience_summary": output.target_audience_summary,
                "rationale": output.rationale,
                "warnings": warnings,
                "generation_run_id": str(run_id),
                "requires_human_review": True,
            }
            await self._content_write.create_content(
                content,
                prompt=brief.key_message,
                tone=brief.tone.value,
                style=output.visual_brief[:500],
                brand_metadata=brand_metadata,
                generated_image_url=image_url,
            )
            payload = output.model_dump(mode="json")
            payload["_prompt_version"] = self._prompt_version
            payload["_brief"] = brief.model_dump(mode="json")
            await self._content_write.create_revision(
                umkm_id=umkm_id,
                content_id=content_id,
                version=1,
                payload=payload,
                changed_by=actor.user_id,
                change_reason="initial generation",
            )
            for evidence in output.evidence:
                if evidence.evidence_id not in valid_evidence_ids:
                    continue
                if evidence.source_kind_enum == SourceKind.RAG_CHUNK:
                    # Chunk ids repeat across generations; persist each
                    # evidence row under a synthetic id and keep the chunk
                    # reference in source_ref.
                    source_id = uuid.uuid4()
                else:
                    try:
                        source_id = uuid.UUID(evidence.evidence_id)
                    except ValueError:
                        # Prefetch evidence ids ("prefetch:...") have no
                        # persisted source row; keep the audit trail with a
                        # synthetic id.
                        source_id = uuid.uuid4()
                source = SourceEvidence.from_evidence(evidence, str(content_id))
                await self._content_write.create_source(
                    umkm_id, source.model_copy(update={"source_id": str(source_id)})
                )

    async def _complete_run(
        self,
        run_id: UUID,
        content_asset_id: UUID,
        state: OrchestrationState,
        retrieved_count: int,
    ) -> None:
        async with self._unit_of_work.begin():
            await self._run_repo.complete_run(
                run_id=run_id,
                content_asset_id=content_asset_id,
                retrieved_context=[
                    {"chunk_id": c.chunk_id, "document_id": c.document_id, "score": c.score}
                    for c in state.retrieved_chunks
                ],
                usage_metadata=state.usage,
                validation_metadata=state.validation_metadata,
                completed_at=datetime.now(UTC),
            )

    async def _fail_run(self, run_id: UUID, error_code: str) -> None:
        async with self._unit_of_work.begin():
            await self._run_repo.fail_run(run_id, error_code, datetime.now(UTC))

    async def _fail_run_quiet(self, run_id: UUID) -> None:
        try:
            await self._fail_run(run_id, "UNKNOWN")
        except Exception:
            logger.exception("failed to mark run failed", extra={"run_id": str(run_id)})

    async def _record_tool_call(self, run_id: UUID, validated: Any, result: Any) -> None:
        try:
            async with self._unit_of_work.begin():
                await self._run_repo.record_tool_call(
                    run_id=run_id,
                    tool_call_id=uuid.uuid4(),
                    tool_name=validated.tool_name,
                    arguments=validated.arguments,
                    status=result.status,
                    result_summary=result.audit_summary(),
                    duration_ms=result.duration_ms,
                )
        except Exception:
            logger.exception("failed to record tool call audit", extra={"run_id": str(run_id)})


def _promotion_output_schema() -> dict:
    """JSON schema for the structured final answer, provider-compatible (no $ref)."""
    schema = PromotionOutput.model_json_schema()
    return _dereference(
        {
            "type": "object",
            "properties": schema.get("properties", {}),
            "required": list(schema.get("required", [])),
        },
        schema.get("$defs", {}),
    )


async def _fetch_image(url: str | None) -> bytes | None:
    """Best-effort product photo fetch; never raises."""
    if not url:
        return None
    try:
        async with httpx.AsyncClient(timeout=5.0, follow_redirects=True) as client:
            response = await client.get(url)
            if response.status_code != 200 or not response.content:
                return None
            if not response.headers.get("content-type", "").startswith("image/"):
                return None
            if len(response.content) > 3 * 1024 * 1024:
                return None
            return response.content
    except Exception:
        logger.debug("product image fetch failed", extra={"url": url[:120]})
        return None


_PLACEHOLDER_HOSTS = {"placehold.co", "dummyimage.com", "via.placeholder.com", "placeholder.com"}


def _is_placeholder_url(url: str) -> bool:
    """Placeholder images are not real photos; treat them as missing."""
    try:
        host = urlparse(url).hostname or ""
    except ValueError:
        host = ""
    return host in _PLACEHOLDER_HOSTS or "placeholder" in url.lower()


async def _product_image(product: Any) -> bytes | None:
    """Real product photo only; missing/placeholder means no photo, and the
    poster model draws the product visual itself from its description."""
    url = getattr(product, "image_url", None)
    if url and not _is_placeholder_url(url):
        return await _fetch_image(url)
    return None


def _no_dash(text: str) -> str:
    """Strip em/en dashes; the model must never emit them in copy."""
    return text.replace("\u2014", ",").replace("\u2013", ",").strip()


def _sanitize_output(output: PromotionOutput) -> PromotionOutput:
    return output.model_copy(
        update={
            "title": _no_dash(output.title),
            "primary_copy": _no_dash(output.primary_copy),
            "caption": _no_dash(output.caption),
            "call_to_action": _no_dash(output.call_to_action),
            "hashtags": [_no_dash(h) for h in output.hashtags],
        }
    )
