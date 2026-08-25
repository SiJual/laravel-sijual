"""Pytest fixtures and shared test utilities for SiPasar."""

from __future__ import annotations

import uuid
from unittest.mock import AsyncMock, MagicMock

import pytest
import pytest_asyncio
from fastapi.testclient import TestClient
from httpx import ASGITransport, AsyncClient

from main import app
from sipasar.models.domain import CompetitorAnalysisResult, CompetitorItem, GeodemografiResult

# ── Sample data ───────────────────────────────────────────────────────────────


SAMPLE_LAT = -7.9666
SAMPLE_LON = 112.6326  # Malang, Jawa Timur
SAMPLE_CATEGORY = "kuliner_kopi"
SAMPLE_RADIUS = 1000
SAMPLE_BUSINESS_PROFILE_ID = uuid.UUID("12345678-1234-5678-1234-567812345678")


SAMPLE_PLACES_RESPONSE = [
    {
        "place_id": "abc1",
        "name": "Kopi Kenangan",
        "category": "kuliner_kopi",
        "latitude": -7.9670,
        "longitude": 112.6330,
        "rating": 4.5,
        "review_count": 120,
        "address": "Jl. Veteran 1",
        "distance_meters": 60.0,
        "source": "google_places",
    },
    {
        "place_id": "abc2",
        "name": "Janji Jiwa",
        "category": "kuliner_kopi",
        "latitude": -7.9680,
        "longitude": 112.6340,
        "rating": 4.2,
        "review_count": 80,
        "address": "Jl. Veteran 3",
        "distance_meters": 200.0,
        "source": "google_places",
    },
    {
        "place_id": "abc3",
        "name": "Fore Coffee",
        "category": "kuliner_kopi",
        "latitude": -7.9690,
        "longitude": 112.6350,
        "rating": 4.0,
        "review_count": 55,
        "address": "Jl. Ijen 5",
        "distance_meters": 450.0,
        "source": "google_places",
    },
]


# ── Domain result fixtures ─────────────────────────────────────────────────────


@pytest.fixture
def sample_competitor_result() -> CompetitorAnalysisResult:
    return CompetitorAnalysisResult(
        count=3,
        avg_rating=4.23,
        competition_score=0.42,
        competition_level="sedang",
        competition_density_per_km2=0.955,
        avg_distance_meters=236.7,
        competitors=[
            CompetitorItem(
                name="Kopi Kenangan",
                category="kuliner_kopi",
                latitude=-7.9670,
                longitude=112.6330,
                rating=4.5,
                review_count=120,
                distance_meters=60.0,
            ),
            CompetitorItem(
                name="Janji Jiwa",
                category="kuliner_kopi",
                latitude=-7.9680,
                longitude=112.6340,
                rating=4.2,
                review_count=80,
                distance_meters=200.0,
            ),
            CompetitorItem(
                name="Fore Coffee",
                category="kuliner_kopi",
                latitude=-7.9690,
                longitude=112.6350,
                rating=4.0,
                review_count=55,
                distance_meters=450.0,
            ),
        ],
    )


@pytest.fixture
def sample_geodemografi_result() -> GeodemografiResult:
    return GeodemografiResult(
        population_estimate=18_500,
        population_density_per_km2=8_319,
        economic_indicator="menengah",
        dominant_consumer_segment="pelajar_mahasiswa",
        area_name="Lowokwaru",
        purchasing_power_proxy=0.55,
    )


# ── HTTP test clients ─────────────────────────────────────────────────────────


@pytest.fixture
def sync_client() -> TestClient:
    """Synchronous test client for simple tests."""
    return TestClient(app)


@pytest_asyncio.fixture
async def async_client() -> AsyncClient:
    """Async HTTPX client for async endpoint tests."""
    async with AsyncClient(
        transport=ASGITransport(app=app), base_url="http://testserver"
    ) as client:
        yield client


# ── Mock provider ─────────────────────────────────────────────────────────────


@pytest.fixture
def mock_places_provider() -> MagicMock:
    provider = MagicMock()
    provider.last_source = "mock"
    provider.nearby_search = AsyncMock(return_value=SAMPLE_PLACES_RESPONSE)
    provider.aclose = AsyncMock()
    return provider
