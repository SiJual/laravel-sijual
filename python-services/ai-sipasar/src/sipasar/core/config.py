"""SiPasar Analytics Service — Core Configuration."""

from functools import lru_cache
from typing import Literal

from pydantic import AliasChoices, Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        env_prefix="SIPASAR_",
        case_sensitive=False,
        extra="ignore",
    )

    # ── App ───────────────────────────────────────────────────────────────────
    APP_NAME: str = "SiPasar Analytics Service"
    APP_VERSION: str = "1.0.0"
    APP_ENV: Literal["development", "staging", "production"] = "development"
    DEBUG: bool = True

    # ── API Server ────────────────────────────────────────────────────────────
    HOST: str = "0.0.0.0"
    PORT: int = 8000

    # ── Google APIs ───────────────────────────────────────────────────────────
    GOOGLE_PLACES_API_KEY: str = Field(
        default="",
        validation_alias=AliasChoices("SIPASAR_GOOGLE_PLACES_API_KEY", "GOOGLE_PLACES_API_KEY"),
    )
    GOOGLE_GEOCODING_API_KEY: str = Field(
        default="",
        validation_alias=AliasChoices(
            "SIPASAR_GOOGLE_GEOCODING_API_KEY", "GOOGLE_GEOCODING_API_KEY"
        ),
    )

    # ── Scoring Weights (Competitor Analysis) — PRD §9.1 ─────────────────────
    # competition_score = normalize(w1*density + w2*avg_rating + w3*(1/avg_dist))
    COMP_W1_DENSITY: float = 0.50
    COMP_W2_AVG_RATING: float = 0.30
    COMP_W3_INVERSE_DIST: float = 0.20

    # ── Scoring Weights (Market Potential) — PRD §9.3 ─────────────────────────
    # market_potential = normalize(a1*demand + a2*purchasing_power + a3*(1-comp) + a4*category_fit)
    MKT_A1_DEMAND: float = 0.35
    MKT_A2_PURCHASING_POWER: float = 0.25
    MKT_A3_COMPETITION: float = 0.25
    MKT_A4_CATEGORY_FIT: float = 0.15

    # ── Market potential thresholds ───────────────────────────────────────────
    MKT_THRESHOLD_HIGH: float = 0.67
    MKT_THRESHOLD_MEDIUM: float = 0.34

    # ── Competition level thresholds ──────────────────────────────────────────
    COMP_THRESHOLD_HIGH: float = 0.67
    COMP_THRESHOLD_MEDIUM: float = 0.34

    # ── External API Timeouts (seconds) ──────────────────────────────────────
    HTTP_TIMEOUT: float = 10.0
    HTTP_MAX_RETRIES: int = 3

    # ── Caching TTL (seconds) ─────────────────────────────────────────────────
    CACHE_POI_TTL: int = 604800  # 7 days
    CACHE_DEMOGRAPHICS_TTL: int = 2592000  # 30 days

    # ── Data paths ────────────────────────────────────────────────────────────
    BPS_GEOJSON_PATH: str = "data/bps_kecamatan.geojson"
    CATEGORY_MAPPING_PATH: str = "data/category_mapping.json"

    # ── CORS ──────────────────────────────────────────────────────────────────
    CORS_ORIGINS: list[str] = ["*"]


@lru_cache
def get_settings() -> Settings:
    """Return cached settings singleton."""
    return Settings()
