"""Command DTOs for use cases."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from uuid import UUID

from sipromo.domain.value_objects.content_type import ApprovalDecision, DocumentType
from sipromo.domain.value_objects.promotion_brief import PromotionBrief


@dataclass(frozen=True)
class AuthenticatedActor:
    user_id: UUID
    umkm_id: UUID
    role: str  # owner | staff | viewer


@dataclass(frozen=True)
class GeneratePromotionCommand:
    brief: PromotionBrief
    request_id: UUID = field(default_factory=lambda: UUID(int=0))  # replaced by use case

    def __post_init__(self) -> None:
        if self.request_id.int == 0:
            import uuid

            object.__setattr__(self, "request_id", uuid.uuid4())


@dataclass(frozen=True)
class ReviseContentCommand:
    content_id: UUID
    feedback: str = ""
    edited_payload: dict | None = None
    change_reason: str | None = None


@dataclass(frozen=True)
class ApproveContentCommand:
    content_id: UUID
    decision: ApprovalDecision
    notes: str | None = None


@dataclass(frozen=True)
class PublishContentCommand:
    content_id: UUID
    platform: str
    scheduled_at: datetime | None = None
    idempotency_key: str | None = None


@dataclass(frozen=True)
class IngestKnowledgeCommand:
    title: str
    document_type: DocumentType
    filename: str
    content_bytes: bytes
    mime_type: str
    source_type: str = "upload"


@dataclass(frozen=True)
class IngestTextKnowledgeCommand:
    title: str
    document_type: DocumentType
    text: str
    source_type: str = "manual"
