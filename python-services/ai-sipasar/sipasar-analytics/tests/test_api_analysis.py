"""Integration tests for /v1/analysis/* endpoints."""

from __future__ import annotations

import uuid
from unittest.mock import AsyncMock, patch

import pytest

from tests.conftest import SAMPLE_BUSINESS_PROFILE_ID, SAMPLE_PLACES_RESPONSE


class TestHealthEndpoint:
    def test_health_returns_ok(self, sync_client) -> None:
        resp = sync_client.get("/v1/health")
        assert resp.status_code == 200
        data = resp.json()
        assert data["status"] == "ok"
        assert "version" in data

    def test_root_endpoint(self, sync_client) -> None:
        resp = sync_client.get("/")
        assert resp.status_code == 200
        data = resp.json()
        assert "service" in data
        assert "docs" in data


class TestRunAnalysis:
    @pytest.mark.asyncio
    async def test_run_analysis_success(self, async_client) -> None:
        with patch(
            "sipasar.services.competitor_service.PlacesProvider"
        ) as mock_cls:
            mock_provider = mock_cls.return_value
            mock_provider.nearby_search = AsyncMock(return_value=SAMPLE_PLACES_RESPONSE)
            mock_provider.aclose = AsyncMock()

            payload = {
                "business_profile_id": str(SAMPLE_BUSINESS_PROFILE_ID),
                "latitude": -7.9666,
                "longitude": 112.6326,
                "category": "kuliner_kopi",
                "radius_meters": 1000,
            }
            resp = await async_client.post("/v1/analysis/run", json=payload)

        assert resp.status_code == 200
        data = resp.json()

        # Check top-level fields
        assert "analysis_id" in data
        assert "competitor" in data
        assert "geodemografi" in data
        assert "market_potential" in data

        # Check competitor section
        comp = data["competitor"]
        assert comp["count"] >= 0
        assert comp["competition_level"] in ("rendah", "sedang", "tinggi")
        assert 0.0 <= comp["competition_score"] <= 1.0

        # Check geodemografi section
        geo = data["geodemografi"]
        assert geo["population_estimate"] > 0
        assert geo["economic_indicator"] in ("rendah", "menengah", "tinggi")

        # Check market_potential section
        mkt = data["market_potential"]
        assert mkt["label"] in ("tinggi", "sedang", "rendah")
        assert 0.0 <= mkt["score"] <= 1.0
        assert len(mkt["narrative"]) > 10

    @pytest.mark.asyncio
    async def test_run_analysis_invalid_radius(self, async_client) -> None:
        payload = {
            "business_profile_id": str(SAMPLE_BUSINESS_PROFILE_ID),
            "latitude": -7.9666,
            "longitude": 112.6326,
            "category": "kuliner_kopi",
            "radius_meters": 750,  # Not in allowed set
        }
        resp = await async_client.post("/v1/analysis/run", json=payload)
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_run_analysis_invalid_coordinates(self, async_client) -> None:
        payload = {
            "business_profile_id": str(SAMPLE_BUSINESS_PROFILE_ID),
            "latitude": 999.0,  # Out of range
            "longitude": 112.6326,
            "category": "kuliner_kopi",
            "radius_meters": 1000,
        }
        resp = await async_client.post("/v1/analysis/run", json=payload)
        assert resp.status_code == 422

    @pytest.mark.asyncio
    async def test_run_analysis_missing_fields(self, async_client) -> None:
        resp = await async_client.post("/v1/analysis/run", json={"latitude": -7.9})
        assert resp.status_code == 422


class TestGetAnalysis:
    @pytest.mark.asyncio
    async def test_get_existing_analysis(self, async_client) -> None:
        # First create an analysis
        with patch(
            "sipasar.services.competitor_service.PlacesProvider"
        ) as mock_cls:
            mock_provider = mock_cls.return_value
            mock_provider.nearby_search = AsyncMock(return_value=SAMPLE_PLACES_RESPONSE)
            mock_provider.aclose = AsyncMock()

            payload = {
                "business_profile_id": str(SAMPLE_BUSINESS_PROFILE_ID),
                "latitude": -7.9666,
                "longitude": 112.6326,
                "category": "kuliner_kopi",
                "radius_meters": 1000,
            }
            create_resp = await async_client.post("/v1/analysis/run", json=payload)

        assert create_resp.status_code == 200
        analysis_id = create_resp.json()["analysis_id"]

        # Then retrieve it
        get_resp = await async_client.get(f"/v1/analysis/{analysis_id}")
        assert get_resp.status_code == 200
        assert get_resp.json()["analysis_id"] == analysis_id

    @pytest.mark.asyncio
    async def test_get_nonexistent_analysis(self, async_client) -> None:
        fake_id = str(uuid.uuid4())
        resp = await async_client.get(f"/v1/analysis/{fake_id}")
        assert resp.status_code == 404

    @pytest.mark.asyncio
    async def test_get_analysis_invalid_uuid(self, async_client) -> None:
        resp = await async_client.get("/v1/analysis/not-a-uuid")
        assert resp.status_code == 422


class TestHistory:
    @pytest.mark.asyncio
    async def test_history_returns_list(self, async_client) -> None:
        resp = await async_client.get(
            "/v1/analysis/history",
            params={"business_profile_id": str(SAMPLE_BUSINESS_PROFILE_ID)},
        )
        assert resp.status_code == 200
        data = resp.json()
        assert "analyses" in data
        assert "total" in data
        assert isinstance(data["analyses"], list)

    @pytest.mark.asyncio
    async def test_history_missing_profile_id(self, async_client) -> None:
        resp = await async_client.get("/v1/analysis/history")
        assert resp.status_code == 422
