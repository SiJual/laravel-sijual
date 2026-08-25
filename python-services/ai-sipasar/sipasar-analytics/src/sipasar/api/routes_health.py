"""SiPasar — Health check route."""

from __future__ import annotations

from fastapi import APIRouter

from sipasar.core.config import get_settings
from sipasar.models.schemas import HealthResponse

router = APIRouter(prefix="/v1", tags=["health"])


@router.get("/health", response_model=HealthResponse, summary="Service health check")
async def health_check() -> HealthResponse:
    """Return service health status and version information."""
    settings = get_settings()
    return HealthResponse(
        status="ok",
        version=settings.APP_VERSION,
        environment=settings.APP_ENV,
        dependencies={
            "google_places": "configured" if settings.GOOGLE_PLACES_API_KEY else "not_configured (OSM fallback active)",
            "google_geocoding": "configured" if settings.GOOGLE_GEOCODING_API_KEY else "not_configured (Nominatim fallback active)",
        },
    )
