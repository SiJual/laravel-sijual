"""Tests for scoring_service.py — PRD §9.3 formula."""

from __future__ import annotations

import pytest

from sipasar.models.domain import CompetitorAnalysisResult, GeodemografiResult, ScoringResult
from sipasar.services.scoring_service import ScoringService, _generate_narrative, _get_category_fit


class TestCategoryFit:
    def test_known_good_fit(self) -> None:
        fit = _get_category_fit("kuliner_kopi", "pelajar_mahasiswa")
        assert fit >= 0.85

    def test_known_bad_fit(self) -> None:
        fit = _get_category_fit("kuliner_warung", "residensial_menengah_atas")
        assert fit < 0.50

    def test_unknown_category_returns_default(self) -> None:
        fit = _get_category_fit("unknown_category", "permukiman_umum")
        assert 0.50 <= fit <= 0.70  # default range

    def test_unknown_segment_returns_default(self) -> None:
        fit = _get_category_fit("kuliner_kopi", "unknown_segment")
        assert 0.50 <= fit <= 0.70


class TestNarrativeGeneration:
    def test_high_potential_narrative(self) -> None:
        narrative = _generate_narrative(
            label="tinggi",
            score=0.75,
            comp_level="rendah",
            pop_estimate=50_000,
            economic_indicator="tinggi",
            dominant_segment="pekerja_kantoran",
            category_fit=0.95,
        )
        assert "tinggi" in narrative.lower()
        assert len(narrative) > 50

    def test_low_potential_narrative(self) -> None:
        narrative = _generate_narrative(
            label="rendah",
            score=0.25,
            comp_level="tinggi",
            pop_estimate=5_000,
            economic_indicator="rendah",
            dominant_segment="permukiman_umum",
            category_fit=0.40,
        )
        assert "rendah" in narrative.lower() or "alternatif" in narrative.lower()
        assert len(narrative) > 50

    def test_narrative_is_string(self) -> None:
        narrative = _generate_narrative(
            label="sedang",
            score=0.50,
            comp_level="sedang",
            pop_estimate=20_000,
            economic_indicator="menengah",
            dominant_segment="permukiman_campuran",
            category_fit=0.65,
        )
        assert isinstance(narrative, str)
        assert len(narrative) > 0


class TestScoringService:
    def _make_low_comp(self) -> CompetitorAnalysisResult:
        return CompetitorAnalysisResult(
            count=2,
            avg_rating=3.5,
            competition_score=0.15,
            competition_level="rendah",
        )

    def _make_high_comp(self) -> CompetitorAnalysisResult:
        return CompetitorAnalysisResult(
            count=30,
            avg_rating=4.5,
            competition_score=0.85,
            competition_level="tinggi",
        )

    def _make_rich_geo(self) -> GeodemografiResult:
        return GeodemografiResult(
            population_estimate=200_000,
            population_density_per_km2=20_000,
            economic_indicator="tinggi",
            dominant_consumer_segment="pekerja_kantoran",
            area_name="Menteng",
            purchasing_power_proxy=0.90,
        )

    def _make_poor_geo(self) -> GeodemografiResult:
        return GeodemografiResult(
            population_estimate=5_000,
            population_density_per_km2=1_000,
            economic_indicator="rendah",
            dominant_consumer_segment="permukiman_umum",
            area_name="Rural Area",
            purchasing_power_proxy=0.25,
        )

    def test_score_range_0_to_1(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        assert 0.0 <= result.score <= 1.0

    def test_high_demand_low_comp_gives_high_label(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        # With high demand (rich geo) and low competition, score should not be "rendah"
        assert result.label in ("tinggi", "sedang")
        # Score should be above the "rendah" threshold
        from sipasar.core.config import get_settings
        assert result.score >= get_settings().MKT_THRESHOLD_MEDIUM

    def test_low_demand_high_comp_gives_low_score(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_high_comp(), self._make_poor_geo(), "kuliner_kopi")
        assert result.score < 0.60

    def test_returns_scoring_result(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        assert isinstance(result, ScoringResult)
        assert result.label in ("tinggi", "sedang", "rendah")
        assert isinstance(result.narrative, str)
        assert len(result.narrative) > 0

    def test_narrative_present(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        assert result.narrative.strip() != ""

    def test_component_fields_populated(self) -> None:
        svc = ScoringService()
        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        assert 0.0 <= result.demand_proxy <= 1.0
        assert 0.0 <= result.purchasing_power_proxy <= 1.0
        assert 0.0 <= result.competition_factor <= 1.0
        assert 0.0 <= result.category_fit <= 1.0

    def test_high_competition_reduces_score(self) -> None:
        svc = ScoringService()
        score_low_comp = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi").score
        score_high_comp = svc.score(self._make_high_comp(), self._make_rich_geo(), "kuliner_kopi").score
        assert score_low_comp > score_high_comp

    def test_label_consistency_with_score(self) -> None:
        from sipasar.core.config import get_settings

        s = get_settings()
        svc = ScoringService()

        result = svc.score(self._make_low_comp(), self._make_rich_geo(), "kuliner_kopi")
        if result.score >= s.MKT_THRESHOLD_HIGH:
            assert result.label == "tinggi"
        elif result.score >= s.MKT_THRESHOLD_MEDIUM:
            assert result.label == "sedang"
        else:
            assert result.label == "rendah"
