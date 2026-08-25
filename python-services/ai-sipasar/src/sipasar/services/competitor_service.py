"""SiPasar — Competitor analysis service.

Implements PRD §9.1: query POI, filter, aggregate, and score competition.
"""

from __future__ import annotations

import logging
import statistics

from sipasar.core.config import get_settings
from sipasar.models.domain import CompetitorAnalysisResult, CompetitorItem
from sipasar.providers.places_provider import PlacesProvider
from sipasar.utils.geo_utils import clamp, competition_density, normalize_to_01

logger = logging.getLogger(__name__)

# ── Scoring constants ─────────────────────────────────────────────────────────

# Assumed max density for normalization (competitors/km²): ~50 in a very dense city area
_MAX_DENSITY = 50.0
# Max avg_rating scale (Google: 1–5, normalized target: 5 = highest competition)
_MAX_RATING = 5.0
# Max inverse distance proxy: 1/very_close_meters; e.g. 1/10 = 0.1 → normalized
_MAX_INV_DIST = 1.0 / 50.0  # assume 50m avg distance is "very close"


def _label_from_score(score: float, high_thresh: float, med_thresh: float) -> str:
    if score >= high_thresh:
        return "tinggi"
    if score >= med_thresh:
        return "sedang"
    return "rendah"


class CompetitorService:
    """Service for competitor analysis (PRD §9.1)."""

    def __init__(self, places_provider: PlacesProvider | None = None) -> None:
        self._provider = places_provider or PlacesProvider()
        self._settings = get_settings()

    async def aclose(self) -> None:
        await self._provider.aclose()

    async def analyze(
        self,
        lat: float,
        lon: float,
        category: str,
        radius_meters: int,
    ) -> CompetitorAnalysisResult:
        """
        Run competitor analysis for the given location and category.

        Steps (PRD §9.1):
        1. Query POI in radius filtered by category
        2. Compute haversine distance for each
        3. Aggregate: count, avg_rating, avg_distance
        4. Compute competition_score + label
        """
        raw_places = await self._provider.nearby_search(lat, lon, radius_meters, category)
        provider_source = getattr(self._provider, "last_source", "")
        data_source = provider_source if isinstance(provider_source, str) else ""

        # Filter to only places within radius (providers may return extras)
        places_in_radius = [p for p in raw_places if p["distance_meters"] <= radius_meters]

        if not places_in_radius:
            logger.info("No competitors found for '%s' within %dm.", category, radius_meters)
            return CompetitorAnalysisResult(
                count=0,
                avg_rating=0.0,
                competition_score=0.0,
                competition_level="rendah",
                competitors=[],
                competition_density_per_km2=0.0,
                avg_distance_meters=0.0,
                data_source=data_source,
            )

        # Build CompetitorItem list
        competitor_items: list[CompetitorItem] = []
        for p in places_in_radius:
            competitor_items.append(
                CompetitorItem(
                    name=p["name"],
                    category=p["category"],
                    latitude=p["latitude"],
                    longitude=p["longitude"],
                    rating=p.get("rating"),
                    review_count=p.get("review_count", 0),
                    distance_meters=p["distance_meters"],
                    place_id=p.get("place_id", ""),
                    address=p.get("address", ""),
                    source=p.get("source", ""),
                    maps_uri=p.get("maps_uri", ""),
                )
            )

        # Sort by distance
        competitor_items.sort(key=lambda c: c.distance_meters)

        # Aggregations
        count = len(competitor_items)
        rated = [c.rating for c in competitor_items if c.rating is not None]
        avg_rating = statistics.mean(rated) if rated else 0.0
        avg_distance = statistics.mean(c.distance_meters for c in competitor_items)

        # Density (PRD §9.1 formula)
        density = competition_density(count, radius_meters)

        # Competition score (PRD §9.1)
        score = self._compute_competition_score(density, avg_rating, avg_distance)
        settings = self._settings
        label = _label_from_score(
            score, settings.COMP_THRESHOLD_HIGH, settings.COMP_THRESHOLD_MEDIUM
        )

        logger.info(
            "Competitor analysis done: count=%d, avg_rating=%.2f, density=%.2f/km², score=%.3f (%s)",
            count,
            avg_rating,
            density,
            score,
            label,
        )

        return CompetitorAnalysisResult(
            count=count,
            avg_rating=round(avg_rating, 2),
            competition_score=round(score, 4),
            competition_level=label,
            competitors=competitor_items,
            competition_density_per_km2=round(density, 4),
            avg_distance_meters=round(avg_distance, 1),
            data_source=data_source,
        )

    def _compute_competition_score(
        self,
        density: float,
        avg_rating: float,
        avg_distance_m: float,
    ) -> float:
        """
        PRD §9.1 formula:

        competition_score = normalize(
            w1 * competition_density +
            w2 * avg_rating_kompetitor +
            w3 * (1 / avg_distance_kompetitor)
        )

        Each component is first normalised to [0,1] before weighting.
        """
        s = self._settings

        # Normalize each component independently to [0, 1]
        norm_density = normalize_to_01(density, 0.0, _MAX_DENSITY)
        norm_rating = normalize_to_01(avg_rating, 0.0, _MAX_RATING)

        if avg_distance_m > 0:
            inv_dist = 1.0 / avg_distance_m
            norm_inv_dist = normalize_to_01(inv_dist, 0.0, _MAX_INV_DIST)
        else:
            norm_inv_dist = 1.0  # competitors at same spot = max proximity

        raw = (
            s.COMP_W1_DENSITY * norm_density
            + s.COMP_W2_AVG_RATING * norm_rating
            + s.COMP_W3_INVERSE_DIST * norm_inv_dist
        )
        return clamp(raw)
