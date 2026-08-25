"""Repository ports (interface segregation).

Read and write sides are separated per ISP. Every repository method receives
the tenant id explicitly; implementations must never query without a tenant
filter. Domain entities are returned - never ORM objects.
"""

from __future__ import annotations

from datetime import date, datetime
from typing import Protocol
from uuid import UUID

from pydantic import BaseModel, Field
from sqlalchemy.ext.asyncio import AsyncSession

from sipromo.domain.entities.knowledge_document import KnowledgeChunk, KnowledgeDocument
from sipromo.domain.entities.promotion_content import PromotionContent
from sipromo.domain.entities.source_evidence import SourceEvidence
from sipromo.domain.value_objects.content_type import (
    ApprovalDecision,
    DocumentStatus,
    DocumentType,
)

# ---------------------------------------------------------------------------
# Fact models returned by business read repositories (sanitized payloads).
# ---------------------------------------------------------------------------


class ProductFact(BaseModel):
    product_id: UUID
    name: str
    category: str | None = None
    price: int | None = None
    status: str = "active"
    stock_level: int | None = None
    image_url: str | None = None
    description: str | None = None


class InventoryEligibility(BaseModel):
    product_id: UUID
    eligible: bool
    reason: str


class BusinessProfile(BaseModel):
    name: str
    business_type: str | None = None
    city: str | None = None
    province: str | None = None
    brand_metadata: dict = Field(default_factory=dict)


class MarketSummary(BaseModel):
    analysis_id: UUID
    title: str
    summary: str
    demographic_data: dict = Field(default_factory=dict)


class CompetitorSummary(BaseModel):
    analysis_id: UUID
    competitors: list[dict] = Field(default_factory=list)


class SalesSummary(BaseModel):
    period_start: date
    period_end: date
    product_id: UUID | None = None
    total_revenue: int = 0
    total_units: int = 0
    transaction_count: int = 0


# ---------------------------------------------------------------------------
# Business facts (legacy schema)
# ---------------------------------------------------------------------------


class BusinessReadRepository(Protocol):
    async def get_business_profile(self, umkm_id: UUID) -> BusinessProfile | None: ...

    async def get_products(self, umkm_id: UUID, product_ids: list[UUID]) -> list[ProductFact]: ...

    async def get_inventory_eligibility(
        self, umkm_id: UUID, product_ids: list[UUID]
    ) -> list[InventoryEligibility]: ...

    async def get_latest_market_summary(self, umkm_id: UUID) -> MarketSummary | None: ...

    async def get_competitor_summary(
        self, umkm_id: UUID, analysis_id: UUID | None = None
    ) -> CompetitorSummary | None: ...

    async def get_sales_summary(
        self,
        umkm_id: UUID,
        product_ids: list[UUID],
        start: date,
        end: date,
    ) -> list[SalesSummary]: ...

    async def get_competitor_terms(self, umkm_id: UUID) -> list[str]: ...


# ---------------------------------------------------------------------------
# Knowledge (new schema)
# ---------------------------------------------------------------------------


class KnowledgeReadRepository(Protocol):
    async def get_document(self, umkm_id: UUID, document_id: UUID) -> KnowledgeDocument | None: ...

    async def list_documents(
        self,
        umkm_id: UUID,
        status: DocumentStatus | None = None,
        document_type: DocumentType | None = None,
    ) -> list[KnowledgeDocument]: ...

    async def document_exists_by_checksum(self, umkm_id: UUID, checksum: str) -> bool: ...

    async def list_chunks_by_document_type(
        self,
        umkm_id: UUID,
        document_types: list[DocumentType],
        limit: int,
    ) -> list[RetrievedRow]: ...

    async def vector_search(
        self,
        umkm_id: UUID,
        query_embedding: list[float],
        limit: int,
        document_types: list[DocumentType] | None = None,
    ) -> list[RetrievedRow]: ...

    async def lexical_search(
        self,
        umkm_id: UUID,
        query: str,
        limit: int,
        document_types: list[DocumentType] | None = None,
    ) -> list[RetrievedRow]: ...

    async def list_chunks_for_document(
        self, umkm_id: UUID, document_id: UUID
    ) -> list[KnowledgeChunk]: ...


class RetrievedRow(BaseModel):
    chunk_id: UUID
    document_id: UUID
    document_type: str
    content: str
    metadata: dict = Field(default_factory=dict)
    score: float


class KnowledgeWriteRepository(Protocol):
    async def create_document(self, document: KnowledgeDocument) -> KnowledgeDocument: ...

    async def update_document_status(
        self, umkm_id: UUID, document_id: UUID, status: DocumentStatus
    ) -> None: ...

    async def update_document_metadata(
        self, umkm_id: UUID, document_id: UUID, **fields: object
    ) -> None: ...

    async def insert_chunks(self, chunks: list[KnowledgeChunk]) -> None: ...

    async def delete_chunks_for_document(self, umkm_id: UUID, document_id: UUID) -> None: ...

    async def archive_document(self, umkm_id: UUID, document_id: UUID) -> None: ...

    async def hard_delete_document(self, umkm_id: UUID, document_id: UUID) -> None: ...


# ---------------------------------------------------------------------------
# Content (legacy content_assets + new trace tables)
# ---------------------------------------------------------------------------


class ContentReadRepository(Protocol):
    async def get_content(self, umkm_id: UUID, content_id: UUID) -> PromotionContent | None: ...

    async def get_latest_revision_payload(self, umkm_id: UUID, content_id: UUID) -> dict | None: ...

    async def get_revision(self, umkm_id: UUID, content_id: UUID, version: int) -> dict | None: ...

    async def get_approved_revision(self, umkm_id: UUID, content_id: UUID) -> dict | None: ...

    async def has_approval(
        self, umkm_id: UUID, content_id: UUID, decision: ApprovalDecision
    ) -> bool: ...

    async def list_sources(self, umkm_id: UUID, content_id: UUID) -> list[SourceEvidence]: ...

    async def list_revisions(self, umkm_id: UUID, content_id: UUID) -> list[dict]: ...


class ContentWriteRepository(Protocol):
    async def create_content(
        self,
        content: PromotionContent,
        *,
        prompt: str,
        tone: str,
        style: str,
        brand_metadata: dict,
        generated_image_url: str | None = None,
    ) -> PromotionContent: ...

    async def create_revision(
        self,
        umkm_id: UUID,
        content_id: UUID,
        version: int,
        payload: dict,
        changed_by: UUID,
        change_reason: str | None,
        parent_revision_id: UUID | None = None,
    ) -> None: ...

    async def create_source(self, umkm_id: UUID, source: SourceEvidence) -> None: ...

    async def create_approval(
        self,
        umkm_id: UUID,
        content_id: UUID,
        decided_by: UUID,
        decision: ApprovalDecision,
        notes: str | None,
    ) -> None: ...

    async def update_content_status(self, umkm_id: UUID, content_id: UUID, status: str) -> None: ...


# ---------------------------------------------------------------------------
# Generation runs (new schema)
# ---------------------------------------------------------------------------


class RunRepository(Protocol):
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
    ) -> None: ...

    async def complete_run(
        self,
        run_id: UUID,
        content_asset_id: UUID | None,
        retrieved_context: list[dict],
        usage_metadata: dict,
        validation_metadata: dict,
        completed_at: datetime,
    ) -> None: ...

    async def fail_run(
        self,
        run_id: UUID,
        error_code: str,
        completed_at: datetime,
    ) -> None: ...

    async def reject_run(self, run_id: UUID, error_code: str, completed_at: datetime) -> None: ...

    async def record_tool_call(
        self,
        run_id: UUID,
        tool_call_id: UUID,
        tool_name: str,
        arguments: dict,
        status: str,
        result_summary: dict,
        duration_ms: int | None,
    ) -> None: ...


# ---------------------------------------------------------------------------
# Memberships (new schema)
# ---------------------------------------------------------------------------


class MembershipRepository(Protocol):
    async def get_membership(self, umkm_id: UUID, user_id: UUID) -> str | None:
        """Role of an active membership, or None if not a member."""
        ...

    async def get_membership_for_user(
        self, user_id: UUID, session: AsyncSession | None = None
    ) -> tuple[UUID, str] | None:
        """First active membership (umkm_id, role) for a user, or None.

        When ``session`` is given the query runs on that session (e.g. a
        short-lived session before the request transaction opens); otherwise
        the active transaction session is used.
        """
        ...


# ---------------------------------------------------------------------------
# Idempotency (new schema)
# ---------------------------------------------------------------------------


class IdempotencyRepository(Protocol):
    async def get_or_create(
        self,
        *,
        scope: str,
        request_hash: str,
        ttl_seconds: int,
    ) -> tuple[str, str | None]:
        """Return (status, response_hash_or_None). 'in_flight' or 'completed'.

        `scope` must already embed the key (e.g. '{user_id}:{route}:{key}').
        """
        ...

    async def complete(self, scope: str, response_hash: str) -> None: ...
