from __future__ import annotations

from pydantic import BaseModel, Field

from sipromo.domain.value_objects.provenance import EvidenceItem


class PromotionOutput(BaseModel):
    """Structured model output. Validated by Pydantic - never parsed from free text."""

    title: str = Field(min_length=1, max_length=200)
    primary_copy: str = Field(min_length=1)
    caption: str = ""
    hashtags: list[str] = Field(default_factory=list, max_length=30)
    call_to_action: str = ""
    visual_brief: str = ""
    target_audience_summary: str = ""
    rationale: list[str] = Field(default_factory=list, max_length=20)
    claims: list[str] = Field(default_factory=list, max_length=50)
    evidence: list[EvidenceItem] = Field(default_factory=list, max_length=50)
    warnings: list[str] = Field(default_factory=list, max_length=30)
    requires_human_review: bool = True

    def model_post_init(self, __context: object) -> None:
        if self.requires_human_review is False:
            raise ValueError("requires_human_review must remain True in the MVP")


class PromotionContent:
    """Content entity persisted as a content_asset row plus revision history."""

    def __init__(
        self,
        *,
        content_id: str,
        umkm_id: str,
        title: str,
        content_type: str,
        primary_copy: str,
        caption: str,
        hashtags: list[str],
        call_to_action: str,
        visual_brief: str,
        status: str,
        version: int,
    ) -> None:
        self.content_id = content_id
        self.umkm_id = umkm_id
        self.title = title
        self.content_type = content_type
        self.primary_copy = primary_copy
        self.caption = caption
        self.hashtags = hashtags
        self.call_to_action = call_to_action
        self.visual_brief = visual_brief
        self.status = status
        self.version = version

    @classmethod
    def from_output(
        cls,
        *,
        content_id: str,
        umkm_id: str,
        content_type: str,
        output: PromotionOutput,
    ) -> PromotionContent:
        return cls(
            content_id=content_id,
            umkm_id=umkm_id,
            title=output.title,
            content_type=content_type,
            primary_copy=output.primary_copy,
            caption=output.caption,
            hashtags=output.hashtags,
            call_to_action=output.call_to_action,
            visual_brief=output.visual_brief,
            status="draft",
            version=1,
        )
