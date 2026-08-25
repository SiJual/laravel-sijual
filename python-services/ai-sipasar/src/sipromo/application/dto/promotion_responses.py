"""Response DTOs returned by use cases."""

from __future__ import annotations

from datetime import datetime
from typing import Any
from uuid import UUID

from pydantic import BaseModel, Field

from sipromo.domain.value_objects.provenance import EvidenceItem


class PromotionDraftDTO(BaseModel):
    content_id: UUID
    generation_run_id: UUID
    status: str
    version: int
    title: str
    primary_copy: str
    caption: str
    hashtags: list[str] = Field(default_factory=list)
    call_to_action: str
    visual_brief: str
    target_audience_summary: str = ""
    rationale: list[str] = Field(default_factory=list)
    claims: list[str] = Field(default_factory=list)
    evidence: list[EvidenceItem] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)
    requires_human_review: bool = True
    image_url: str | None = None
    created_at: datetime | None = None


class RevisionDTO(BaseModel):
    content_id: UUID
    version: int
    payload: dict[str, Any]
    change_reason: str | None = None
    created_at: datetime


class ApprovalDTO(BaseModel):
    content_id: UUID
    decision: str
    notes: str | None = None
    decided_by: UUID
    created_at: datetime


class PublishJobDTO(BaseModel):
    job_id: UUID
    content_id: UUID
    platform: str
    status: str
    scheduled_at: datetime | None = None


class KnowledgeDocumentDTO(BaseModel):
    document_id: UUID
    umkm_id: UUID
    title: str
    document_type: str
    source_type: str
    status: str
    checksum_sha256: str | None = None
    mime_type: str | None = None
    chunk_count: int = 0
    created_at: datetime | None = None


class HealthStatusDTO(BaseModel):
    status: str
    components: dict[str, str] = Field(default_factory=dict)
