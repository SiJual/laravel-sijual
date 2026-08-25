"""SiPasar — Geodemographic analysis service.

Implements PRD §9.2: spatial lookup, population estimation, consumer segment.
"""

from __future__ import annotations

import logging

from sipasar.models.domain import GeodemografiResult
from sipasar.providers import bps_provider

logger = logging.getLogger(__name__)

# ── Consumer segment inference from POI context ───────────────────────────────

# POI-based segment override: if Google Places / OSM returns many of these
# types in the area, we infer the dominant consumer segment.
# For simplicity in v1, we rely primarily on BPS land use classification.
_LAND_USE_TO_SEGMENT: dict[str, str] = {
    "perkantoran": "pekerja_kantoran",
    "permukiman_elite": "residensial_menengah_atas",
    "permukiman_padat": "permukiman_campuran",
    "permukiman_dan_kampus": "pelajar_mahasiswa",
    "pusat_kota_perdagangan": "pekerja_dan_pedagang",
    "perdagangan": "pekerja_dan_pedagang",
    "campuran": "permukiman_umum",
    "permukiman_umum": "permukiman_umum",
}


def _map_consumer_segment(land_use: str, bps_segment: str) -> str:
    """Return the best consumer segment label."""
    # BPS already has dominant_consumer_segment; use it directly if available
    if bps_segment:
        return bps_segment
    return _LAND_USE_TO_SEGMENT.get(land_use, "permukiman_umum")


class GeodemografiService:
    """Service for geodemographic analysis (PRD §9.2)."""

    async def analyze(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
    ) -> GeodemografiResult:
        """
        Run geodemographic analysis for the given location.

        Steps (PRD §9.2):
        1. Spatial join → kecamatan properties
        2. Estimate population in radius via areal interpolation
        3. Return structured GeodemografiResult
        """
        kec_props = bps_provider.get_kecamatan_at(lat, lon)

        if kec_props is None:
            kec_props = bps_provider._fallback_properties()

        pop_estimate = bps_provider.estimate_population_in_radius(kec_props, radius_meters)
        density = float(kec_props.get("population_density_per_km2", 5_000))
        economic_indicator: str = str(kec_props.get("economic_indicator", "menengah"))

        land_use: str = str(kec_props.get("dominant_land_use", "campuran"))
        bps_segment: str = str(kec_props.get("dominant_consumer_segment", ""))
        consumer_segment = _map_consumer_segment(land_use, bps_segment)

        area_name = kec_props.get("kecamatan", "")

        # Purchasing power proxy: map economic_indicator text → float [0,1]
        purchasing_power = self._economic_indicator_to_proxy(economic_indicator)

        logger.info(
            "Geodemografi: area=%s, pop_estimate=%d, density=%.0f/km², segment=%s, econ=%s",
            area_name,
            pop_estimate,
            density,
            consumer_segment,
            economic_indicator,
        )

        return GeodemografiResult(
            population_estimate=pop_estimate,
            population_density_per_km2=round(density, 1),
            economic_indicator=economic_indicator,
            dominant_consumer_segment=consumer_segment,
            area_name=area_name,
            purchasing_power_proxy=purchasing_power,
        )

    @staticmethod
    def _economic_indicator_to_proxy(indicator: str) -> float:
        """Convert text economic indicator to a normalized float proxy."""
        mapping = {
            "rendah": 0.25,
            "menengah": 0.55,
            "tinggi": 0.85,
        }
        return mapping.get(indicator.lower(), 0.50)
