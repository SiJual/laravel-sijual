"""Content repository: legacy content_assets + new trace tables
(revisions, sources, approvals)."""

from __future__ import annotations

import json
import uuid
from datetime import datetime
from uuid import UUID

from sqlalchemy import select, text

from sipromo.application.ports.repositories import (
    ContentReadRepository,
    ContentWriteRepository,
)
from sipromo.domain.entities.promotion_content import PromotionContent
from sipromo.domain.entities.source_evidence import SourceEvidence
from sipromo.domain.value_objects.content_type import ApprovalDecision, SourceKind
from sipromo.infrastructure.db.models.legacy import ContentAsset
from sipromo.infrastructure.db.models.new import (
    ContentApproval,
    ContentRevision,
    ContentSource,
)
from sipromo.infrastructure.db.session import get_current_session


def _to_domain(row: ContentAsset) -> PromotionContent:
    return PromotionContent(
        content_id=str(row.id),
        umkm_id=str(row.umkm_id),
        title=row.title or "",
        content_type=row.content_type,
        primary_copy=row.generated_text or "",
        caption=row.caption or "",
        hashtags=(row.hashtags or "").split("\n") if row.hashtags else [],
        call_to_action="",
        visual_brief=row.style or "",
        status=row.status,
        version=row.version,
    )


class ContentRepository(ContentReadRepository, ContentWriteRepository):
    async def get_content(self, umkm_id: UUID, content_id: UUID) -> PromotionContent | None:
        session = get_current_session()
        result = await session.execute(
            select(ContentAsset).where(
                ContentAsset.umkm_id == umkm_id,
                ContentAsset.id == content_id,
            )
        )
        row = result.scalar_one_or_none()
        return _to_domain(row) if row is not None else None

    async def get_latest_revision_payload(self, umkm_id: UUID, content_id: UUID) -> dict | None:
        session = get_current_session()
        result = await session.execute(
            select(ContentRevision.payload, ContentRevision.id)
            .join(ContentAsset, ContentAsset.id == ContentRevision.content_asset_id)
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentRevision.content_asset_id == content_id,
            )
            .order_by(ContentRevision.version.desc())
            .limit(1)
        )
        row = result.first()
        return dict(row.payload) if row is not None else None

    async def get_revision(self, umkm_id: UUID, content_id: UUID, version: int) -> dict | None:
        session = get_current_session()
        result = await session.execute(
            select(ContentRevision.payload)
            .join(ContentAsset, ContentAsset.id == ContentRevision.content_asset_id)
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentRevision.content_asset_id == content_id,
                ContentRevision.version == version,
            )
        )
        row = result.scalar_one_or_none()
        return dict(row) if row is not None else None

    async def get_approved_revision(self, umkm_id: UUID, content_id: UUID) -> dict | None:
        session = get_current_session()
        result = await session.execute(
            select(ContentRevision.payload, ContentRevision.version)
            .join(ContentAsset, ContentAsset.id == ContentRevision.content_asset_id)
            .join(
                ContentApproval,
                ContentApproval.content_asset_id == ContentRevision.content_asset_id,
            )
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentRevision.content_asset_id == content_id,
                ContentApproval.decision == ApprovalDecision.APPROVED.value,
                ContentApproval.revision_id.is_(None)
                | (ContentApproval.revision_id == ContentRevision.id),
            )
            .order_by(ContentRevision.version.desc())
            .limit(1)
        )
        row = result.first()
        return dict(row.payload) if row is not None else None

    async def has_approval(
        self, umkm_id: UUID, content_id: UUID, decision: ApprovalDecision
    ) -> bool:
        session = get_current_session()
        result = await session.execute(
            select(ContentApproval.id)
            .join(ContentAsset, ContentAsset.id == ContentApproval.content_asset_id)
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentApproval.content_asset_id == content_id,
                ContentApproval.decision == decision.value,
            )
            .limit(1)
        )
        return result.first() is not None

    async def list_sources(self, umkm_id: UUID, content_id: UUID) -> list[SourceEvidence]:
        session = get_current_session()
        result = await session.execute(
            select(ContentSource)
            .join(ContentAsset, ContentAsset.id == ContentSource.content_asset_id)
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentSource.content_asset_id == content_id,
            )
        )
        return [
            SourceEvidence(
                source_id=str(row.id),
                content_asset_id=str(row.content_asset_id),
                source_kind=SourceKind(row.source_kind),
                source_ref=row.source_ref,
                chunk_id=str(row.chunk_id) if row.chunk_id else None,
                claim_keys=list(row.claim_keys or []),
                relevance_score=row.relevance_score,
                excerpt=row.excerpt,
            )
            for row in result.scalars().all()
        ]

    async def list_revisions(self, umkm_id: UUID, content_id: UUID) -> list[dict]:
        session = get_current_session()
        result = await session.execute(
            select(ContentRevision)
            .join(ContentAsset, ContentAsset.id == ContentRevision.content_asset_id)
            .where(
                ContentAsset.umkm_id == umkm_id,
                ContentRevision.content_asset_id == content_id,
            )
            .order_by(ContentRevision.version)
        )
        return [
            {
                "version": row.version,
                "change_reason": row.change_reason,
                "created_at": row.created_at.isoformat(),
            }
            for row in result.scalars().all()
        ]

    async def create_content(
        self,
        content: PromotionContent,
        *,
        prompt: str,
        tone: str,
        style: str,
        brand_metadata: dict,
        generated_image_url: str | None = None,
    ) -> PromotionContent:
        session = get_current_session()
        row = ContentAsset(
            id=UUID(content.content_id),
            umkm_id=UUID(content.umkm_id),
            title=content.title,
            content_type=content.content_type,
            prompt=prompt,
            generated_text=content.primary_copy,
            generated_image_url=generated_image_url[:255] if generated_image_url else None,
            caption=content.caption,
            hashtags="\n".join(content.hashtags),
            tone=tone,
            style=style[:255],
            version=content.version,
            status="draft",
            brand_metadata=brand_metadata,
        )
        session.add(row)
        await session.flush()
        return content

    async def create_revision(
        self,
        umkm_id: UUID,
        content_id: UUID,
        version: int,
        payload: dict,
        changed_by: UUID,
        change_reason: str | None,
        parent_revision_id: UUID | None = None,
    ) -> None:
        session = get_current_session()
        revision = ContentRevision(
            id=uuid.uuid4(),
            content_asset_id=content_id,
            version=version,
            parent_revision_id=parent_revision_id,
            changed_by=changed_by,
            change_reason=change_reason,
            payload=payload,
        )
        session.add(revision)
        await session.execute(
            text("UPDATE content_assets SET version = :version WHERE id = :content_id"),
            {"version": version, "content_id": str(content_id)},
        )

    async def create_source(self, umkm_id: UUID, source: SourceEvidence) -> None:
        session = get_current_session()
        row = ContentSource(
            id=UUID(source.source_id),
            content_asset_id=UUID(source.content_asset_id),
            source_kind=source.source_kind.value,
            source_ref=source.source_ref,
            chunk_id=UUID(source.chunk_id) if source.chunk_id else None,
            claim_keys=source.claim_keys,
            relevance_score=source.relevance_score,
            excerpt=source.excerpt,
        )
        session.add(row)
        await session.flush()

    async def create_approval(
        self,
        umkm_id: UUID,
        content_id: UUID,
        decided_by: UUID,
        decision: ApprovalDecision,
        notes: str | None,
    ) -> None:
        session = get_current_session()
        row = ContentApproval(
            id=uuid.uuid4(),
            content_asset_id=content_id,
            decided_by=decided_by,
            decision=decision.value,
            notes=notes,
        )
        session.add(row)
        await session.flush()

    async def update_content_status(self, umkm_id: UUID, content_id: UUID, status: str) -> None:
        session = get_current_session()
        await session.execute(
            text(
                "UPDATE content_assets SET status = :status "
                "WHERE umkm_id = :umkm_id AND id = :content_id"
            ),
            {
                "umkm_id": str(umkm_id),
                "content_id": str(content_id),
                "status": status,
            },
        )


class RunRepositoryImpl:
    async def create_run(
        self,
        run_id: UUID,
        umkm_id: UUID,
        user_id: UUID | None,
        request_id: UUID,
        model_provider: str,
        model_name: str,
        prompt_version: str,
        brief: dict,
    ) -> None:
        from sipromo.infrastructure.db.models.new import GenerationRun

        session = get_current_session()
        row = GenerationRun(
            id=run_id,
            umkm_id=umkm_id,
            user_id=user_id,
            request_id=request_id,
            model_provider=model_provider,
            model_name=model_name,
            prompt_version=prompt_version,
            status="started",
            brief=brief,
        )
        session.add(row)
        await session.flush()

    async def complete_run(
        self,
        run_id: UUID,
        content_asset_id: UUID | None,
        retrieved_context: list[dict],
        usage_metadata: dict,
        validation_metadata: dict,
        completed_at: datetime,
    ) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE generation_runs
                SET status = 'completed',
                    content_asset_id = :content_asset_id,
                    retrieved_context = :retrieved_context,
                    usage_metadata = :usage_metadata,
                    validation_metadata = :validation_metadata,
                    completed_at = :completed_at
                WHERE id = :run_id
            """),
            {
                "run_id": str(run_id),
                "content_asset_id": str(content_asset_id) if content_asset_id else None,
                "retrieved_context": json.dumps(retrieved_context, ensure_ascii=False, default=str),
                "usage_metadata": json.dumps(usage_metadata, ensure_ascii=False, default=str),
                "validation_metadata": json.dumps(
                    validation_metadata, ensure_ascii=False, default=str
                ),
                "completed_at": completed_at,
            },
        )

    async def fail_run(self, run_id: UUID, error_code: str, completed_at: datetime) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE generation_runs
                SET status = 'failed', error_code = :error_code, completed_at = :completed_at
                WHERE id = :run_id
            """),
            {
                "run_id": str(run_id),
                "error_code": error_code,
                "completed_at": completed_at,
            },
        )

    async def reject_run(self, run_id: UUID, error_code: str, completed_at: datetime) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE generation_runs
                SET status = 'rejected', error_code = :error_code, completed_at = :completed_at
                WHERE id = :run_id
            """),
            {
                "run_id": str(run_id),
                "error_code": error_code,
                "completed_at": completed_at,
            },
        )

    async def record_tool_call(
        self,
        run_id: UUID,
        tool_call_id: UUID,
        tool_name: str,
        arguments: dict,
        status: str,
        result_summary: dict,
        duration_ms: int | None,
    ) -> None:
        from sipromo.infrastructure.db.models.new import GenerationToolCall

        session = get_current_session()
        row = GenerationToolCall(
            id=tool_call_id,
            generation_run_id=run_id,
            tool_name=tool_name,
            arguments=arguments,
            result_summary=result_summary,
            status=status,
            duration_ms=duration_ms,
        )
        session.add(row)
        await session.flush()
