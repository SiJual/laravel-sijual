from __future__ import annotations

from pydantic import BaseModel, Field

from sipromo.domain.value_objects.content_type import SourceKind
from sipromo.domain.value_objects.provenance import EvidenceItem


class SourceEvidence(BaseModel):
    """A persisted source citation linking a content asset to evidence."""

    source_id: str
    content_asset_id: str
    source_kind: SourceKind
    source_ref: str
    chunk_id: str | None = None
    claim_keys: list[str] = Field(default_factory=list)
    relevance_score: float | None = None
    excerpt: str | None = None

    @classmethod
    def from_evidence(cls, evidence: EvidenceItem, content_asset_id: str) -> SourceEvidence:
        return cls(
            source_id=evidence.evidence_id,
            content_asset_id=content_asset_id,
            source_kind=evidence.source_kind_enum,
            source_ref=evidence.source_ref,
            claim_keys=evidence.supported_claims,
        )
