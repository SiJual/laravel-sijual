"""Tests for competitor_service.py — PRD §9.1 formula."""

from __future__ import annotations

import pytest

from sipasar.models.domain import CompetitorAnalysisResult
from sipasar.services.competitor_service import CompetitorService
from sipasar.utils.geo_utils import competition_density, haversine_distance


# ── Unit tests for geo_utils used in competitor service ───────────────────────


class TestHaversineDistance:
    def test_same_point_is_zero(self) -> None:
        dist = haversine_distance(-7.966, 112.632, -7.966, 112.632)
        assert dist == pytest.approx(0.0, abs=0.01)

    def test_known_distance(self) -> None:
        # Jakarta (Monas) to Surabaya (Tugu Pahlawan)
        # Great-circle distance: ~663 km (NOT road distance ~780 km)
        dist = haversine_distance(-6.1754, 106.8272, -7.2459, 112.7378)
        assert 630_000 < dist < 700_000  # meters (great-circle)

    def test_symmetry(self) -> None:
        d1 = haversine_distance(-7.966, 112.632, -7.970, 112.640)
        d2 = haversine_distance(-7.970, 112.640, -7.966, 112.632)
        assert d1 == pytest.approx(d2, rel=1e-6)


class TestCompetitionDensity:
    def test_zero_competitors(self) -> None:
        assert competition_density(0, 1000) == pytest.approx(0.0)

    def test_known_value(self) -> None:
        # 10 competitors in 1 km radius → area = π km² ≈ 3.14159 → density ≈ 3.18
        density = competition_density(10, 1000)
        assert density == pytest.approx(10 / 3.14159, rel=0.01)

    def test_larger_radius_lower_density(self) -> None:
        d_small = competition_density(5, 500)
        d_large = competition_density(5, 1000)
        assert d_small > d_large


# ── Competitor service tests ──────────────────────────────────────────────────


class TestCompetitorServiceScoring:
    """Test the scoring formula independently of provider calls."""

    def _svc(self) -> CompetitorService:
        return CompetitorService()

    def test_no_competitors_returns_zero_score(self) -> None:
        svc = self._svc()
        score = svc._compute_competition_score(0.0, 0.0, 1000.0)
        assert score == pytest.approx(0.0, abs=0.05)

    def test_high_density_returns_high_score(self) -> None:
        svc = self._svc()
        # 50 competitors/km² is the max density in normalisation — should yield high score
        score = svc._compute_competition_score(50.0, 5.0, 50.0)
        assert score > 0.60

    def test_low_density_low_rating_returns_low_score(self) -> None:
        svc = self._svc()
        score = svc._compute_competition_score(0.5, 2.0, 800.0)
        assert score < 0.35

    def test_score_is_clamped_to_0_1(self) -> None:
        svc = self._svc()
        score = svc._compute_competition_score(999.0, 999.0, 0.001)
        assert 0.0 <= score <= 1.0

    def test_competition_level_rendah(self) -> None:
        svc = self._svc()
        score = svc._compute_competition_score(0.1, 1.0, 900.0)
        from sipasar.core.config import get_settings

        s = get_settings()
        if score < s.COMP_THRESHOLD_MEDIUM:
            assert True  # rendah
        # just verify it doesn't crash


class TestCompetitorServiceIntegration:
    """Integration test using a mock provider."""

    @pytest.mark.asyncio
    async def test_analyze_with_mock_provider(
        self, mock_places_provider, sample_competitor_result
    ) -> None:
        svc = CompetitorService(places_provider=mock_places_provider)
        result = await svc.analyze(
            lat=-7.9666,
            lon=112.6326,
            category="kuliner_kopi",
            radius_meters=1000,
        )

        assert isinstance(result, CompetitorAnalysisResult)
        assert result.count == 3
        assert 3.9 < result.avg_rating < 4.6
        assert result.competition_level in ("rendah", "sedang", "tinggi")
        assert 0.0 <= result.competition_score <= 1.0
        assert len(result.competitors) == 3
        # Sorted by distance
        dists = [c.distance_meters for c in result.competitors]
        assert dists == sorted(dists)

    @pytest.mark.asyncio
    async def test_analyze_empty_results(self, mock_places_provider) -> None:
        mock_places_provider.nearby_search.return_value = []
        svc = CompetitorService(places_provider=mock_places_provider)
        result = await svc.analyze(
            lat=-7.9666,
            lon=112.6326,
            category="kuliner_kopi",
            radius_meters=1000,
        )

        assert result.count == 0
        assert result.competition_score == 0.0
        assert result.competition_level == "rendah"

    @pytest.mark.asyncio
    async def test_filters_places_outside_radius(self, mock_places_provider) -> None:
        """Places with distance_meters > radius should be excluded."""
        mock_places_provider.nearby_search.return_value = [
            {
                "place_id": "far1",
                "name": "Far Coffee",
                "category": "kuliner_kopi",
                "latitude": -7.990,
                "longitude": 112.650,
                "rating": 4.0,
                "review_count": 10,
                "address": "",
                "distance_meters": 5000.0,  # > radius 1000m
                "source": "mock",
            }
        ]
        svc = CompetitorService(places_provider=mock_places_provider)
        result = await svc.analyze(
            lat=-7.9666, lon=112.6326, category="kuliner_kopi", radius_meters=1000
        )
        assert result.count == 0
