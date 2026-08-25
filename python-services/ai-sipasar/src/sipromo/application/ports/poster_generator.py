"""Poster pipeline ports (previous session state, restored 2026-08-20 18:55).

The image model draws the COMPLETE poster: poster copy (headline, message,
CTA, hashtags) is designed directly into the image by the model, using real
product photos as visual references when available.
"""

from __future__ import annotations

from typing import Protocol

from pydantic import BaseModel, Field


class ProductMedia(BaseModel):
    name: str
    description: str = ""
    image_bytes: bytes | None = None


class PosterSpec(BaseModel):
    """Poster generation spec passed to the image model."""

    brand_name: str
    headline: str
    message: str
    product_media: list[ProductMedia] = Field(default_factory=list)
    call_to_action: str = ""
    hashtags: list[str] = Field(default_factory=list)
    tone: str = "friendly"


class PosterGeneratorPort(Protocol):
    def generate(self, spec: PosterSpec) -> bytes: ...


class PosterGenerationError(Exception):
    """Raised when the image model produced no poster image."""
