from __future__ import annotations

from uuid import UUID

from pydantic import BaseModel, Field, field_validator

from sipromo.domain.value_objects.content_type import (
    ContentType,
    Language,
    Objective,
    Platform,
    Tone,
)


class PromotionBrief(BaseModel):
    """Validated user brief. Never carries umkm_id - it comes from auth context."""

    objective: Objective
    content_type: ContentType
    platform: Platform = Platform.GENERIC
    product_ids: list[UUID] = Field(min_length=1, max_length=10)
    target_audience: str | None = Field(default=None, max_length=500)
    tone: Tone
    language: Language = Language.ID
    key_message: str = Field(min_length=5, max_length=1000)
    call_to_action: str | None = Field(default=None, max_length=300)
    constraints: list[str] = Field(default_factory=list, max_length=20)
    include_market_context: bool = True
    include_business_performance: bool = False

    @field_validator("constraints")
    @classmethod
    def _constraints_not_blank(cls, value: list[str]) -> list[str]:
        cleaned = [c.strip() for c in value if c.strip()]
        if len(cleaned) > 20:
            raise ValueError("At most 20 constraints allowed")
        return cleaned

    @field_validator("key_message", "target_audience", "call_to_action")
    @classmethod
    def _strip_text(cls, value: str | None) -> str | None:
        return value.strip() if value is not None else None

    def as_prompt_block(self) -> str:
        lines = [
            f"objective={self.objective.value}",
            f"content_type={self.content_type.value}",
            f"platform={self.platform.value}",
            f"product_ids={[str(p) for p in self.product_ids]}",
            f"tone={self.tone.value}",
            f"language={self.language.value}",
            f"key_message={self.key_message}",
        ]
        if self.target_audience:
            lines.append(f"target_audience={self.target_audience}")
        if self.call_to_action:
            lines.append(f"call_to_action={self.call_to_action}")
        if self.constraints:
            lines.append(f"constraints={self.constraints}")
        return "\n".join(lines)
