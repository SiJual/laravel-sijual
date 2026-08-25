from __future__ import annotations

from datetime import UTC, datetime
from typing import Literal

from pydantic import BaseModel, Field

from sipromo.domain.value_objects.content_type import SourceKind


class EvidenceItem(BaseModel):
    evidence_id: str
    source_kind: Literal["tool_result", "rag_chunk", "user_input"]
    source_ref: str
    supported_claims: list[str] = Field(default_factory=list)

    @property
    def source_kind_enum(self) -> SourceKind:
        return SourceKind(self.source_kind)


class Provenance(BaseModel):
    """Accountability record for a generated content asset."""

    generation_run_id: str
    prompt_version: str
    model_provider: str
    model_name: str
    requested_at: datetime = Field(default_factory=lambda: datetime.now(UTC))

    def to_json_compatible(self) -> dict:
        return self.model_dump(mode="json")
