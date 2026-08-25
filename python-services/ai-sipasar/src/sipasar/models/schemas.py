"""SiPasar — Pydantic v2 request/response schemas (API contract)."""

from __future__ import annotations

import uuid
from datetime import datetime
from typing import Any

from pydantic import BaseModel, Field, field_validator

# ── Request Models ────────────────────────────────────────────────────────────


class RunAnalysisRequest(BaseModel):
    """POST /v1/analysis/run — request body."""

    business_profile_id: uuid.UUID = Field(..., description="UUID profil bisnis")
    latitude: float = Field(..., ge=-90, le=90, description="Latitude lokasi bisnis")
    longitude: float = Field(..., ge=-180, le=180, description="Longitude lokasi bisnis")
    category: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Kategori usaha (e.g. 'kuliner_kopi', 'retail_fashion')",
    )
    radius_meters: int = Field(
        default=1000,
        ge=500,
        le=10000,
        description="Radius analisis dalam meter (500/1000/3000/5000/10000)",
    )

    @field_validator("radius_meters")
    @classmethod
    def validate_radius(cls, v: int) -> int:
        allowed = {500, 1000, 3000, 5000, 10000}
        if v not in allowed:
            raise ValueError(f"radius_meters harus salah satu dari: {sorted(allowed)}")
        return v

    @field_validator("category")
    @classmethod
    def validate_category(cls, v: str) -> str:
        return v.strip().lower()


class RerunAnalysisRequest(BaseModel):
    """POST /v1/analysis/{id}/rerun — optional overrides."""

    radius_meters: int | None = Field(
        default=None,
        ge=500,
        le=10000,
        description="Ganti radius untuk analisis ulang",
    )

    @field_validator("radius_meters")
    @classmethod
    def validate_radius(cls, v: int | None) -> int | None:
        if v is not None:
            allowed = {500, 1000, 3000, 5000, 10000}
            if v not in allowed:
                raise ValueError(f"radius_meters harus salah satu dari: {sorted(allowed)}")
        return v


# ── Response Sub-models ────────────────────────────────────────────────────────


class CompetitorItemResponse(BaseModel):
    name: str
    category: str
    rating: float | None
    distance_m: float
    latitude: float | None = None
    longitude: float | None = None
    place_id: str = ""
    address: str = ""
    source: str = ""
    maps_uri: str = ""


class CompetitorResponse(BaseModel):
    count: int
    avg_rating: float
    competition_level: str  # "rendah" | "sedang" | "tinggi"
    competition_score: float
    competition_density_per_km2: float
    competitors: list[CompetitorItemResponse]
    data_source: str = ""
    provider_status: str = "ok"


class GeodemografiResponse(BaseModel):
    population_estimate: int
    population_density_per_km2: float
    economic_indicator: str  # "rendah" | "menengah" | "tinggi"
    dominant_consumer_segment: str
    area_name: str = ""


class MarketPotentialResponse(BaseModel):
    score: float
    label: str  # "tinggi" | "sedang" | "rendah"
    narrative: str


# ── Top-level Response Models ──────────────────────────────────────────────────


class AnalysisResponse(BaseModel):
    """Response for POST /v1/analysis/run and GET /v1/analysis/{id}."""

    analysis_id: uuid.UUID
    business_profile_id: uuid.UUID
    radius_meters: int
    category: str
    latitude: float
    longitude: float
    competitor: CompetitorResponse
    geodemografi: GeodemografiResponse
    market_potential: MarketPotentialResponse
    created_at: datetime


class AnalysisHistoryItem(BaseModel):
    analysis_id: uuid.UUID
    radius_meters: int
    category: str
    market_potential_label: str
    market_potential_score: float
    competition_level: str
    created_at: datetime


class AnalysisHistoryResponse(BaseModel):
    business_profile_id: uuid.UUID
    analyses: list[AnalysisHistoryItem]
    total: int


class HealthResponse(BaseModel):
    status: str
    version: str
    environment: str
    dependencies: dict[str, Any] = {}
