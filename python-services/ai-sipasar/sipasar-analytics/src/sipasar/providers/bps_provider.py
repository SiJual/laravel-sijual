"""SiPasar — BPS (Badan Pusat Statistik) demographic data provider.

Loads a local GeoJSON file with kecamatan-level demographic data.
Uses geopandas for spatial operations (point-in-polygon).
Data is loaded and cached in memory after the first access.
"""

from __future__ import annotations

import logging
import math
from pathlib import Path
from typing import Any

from sipasar.core.config import get_settings
from sipasar.utils.geo_utils import circle_area_km2

logger = logging.getLogger(__name__)

# ── Lazy-loaded cache ─────────────────────────────────────────────────────────

_gdf_cache: Any | None = None  # geopandas.GeoDataFrame


def _load_geodataframe() -> Any:
    """Load and cache the BPS GeoDataFrame from disk."""
    global _gdf_cache
    if _gdf_cache is not None:
        return _gdf_cache

    try:
        import geopandas as gpd  # noqa: PLC0415

        settings = get_settings()
        path = Path(settings.BPS_GEOJSON_PATH)

        if not path.exists():
            logger.error("BPS GeoJSON not found at %s — using fallback.", path)
            return None

        gdf = gpd.read_file(path)
        gdf = gdf.set_crs("EPSG:4326", allow_override=True)
        _gdf_cache = gdf
        logger.info("BPS GeoJSON loaded: %d kecamatan features.", len(gdf))
        return gdf
    except ImportError:
        logger.error("geopandas not installed; BPS spatial features unavailable.")
        return None
    except Exception as exc:
        logger.error("Failed to load BPS GeoJSON: %s", exc)
        return None


# ── Public API ────────────────────────────────────────────────────────────────


def get_kecamatan_at(lat: float, lon: float) -> dict[str, Any] | None:
    """
    Find the kecamatan properties for a given point (lat, lon).

    Uses point-in-polygon spatial join. Returns the last (fallback) feature
    if no specific match is found (the GeoJSON should include a worldwide
    fallback polygon as the last feature).
    """
    gdf = _load_geodataframe()
    if gdf is None:
        return _fallback_properties()

    try:
        from shapely.geometry import Point  # noqa: PLC0415

        point = Point(lon, lat)  # shapely uses (x=lon, y=lat)
        matches = gdf[gdf.geometry.contains(point)]

        if matches.empty:
            logger.info("No kecamatan match for (%.6f, %.6f); using fallback.", lat, lon)
            # Use last row (designed as global fallback in sample data)
            row = gdf.iloc[-1]
        else:
            row = matches.iloc[0]

        return row.drop("geometry").to_dict()
    except Exception as exc:
        logger.warning("Spatial lookup failed: %s", exc)
        return _fallback_properties()


def estimate_population_in_radius(
    kecamatan_props: dict[str, Any],
    radius_meters: float,
) -> int:
    """
    Estimate the population within a given radius using areal interpolation.

    PRD §9.2: proportional interpolation based on circle area vs kecamatan area.
    """
    total_pop: int = int(kecamatan_props.get("population", 50_000))
    kec_area_km2: float = float(kecamatan_props.get("area_km2", 10.0))

    radius_area_km2 = circle_area_km2(radius_meters)

    # The radius area cannot exceed the kecamatan area (capped at 100%)
    coverage_ratio = min(1.0, radius_area_km2 / kec_area_km2) if kec_area_km2 > 0 else 0.5

    return max(100, int(total_pop * coverage_ratio))


def _fallback_properties() -> dict[str, Any]:
    """Fallback demographic data when BPS lookup fails."""
    return {
        "kecamatan": "Unknown",
        "kabupaten": "Unknown",
        "provinsi": "Indonesia",
        "population": 50_000,
        "area_km2": 10.0,
        "population_density_per_km2": 5_000,
        "umr_monthly_idr": 2_500_000,
        "economic_indicator": "menengah",
        "dominant_land_use": "campuran",
        "dominant_consumer_segment": "permukiman_umum",
        "purchasing_power_index": 0.40,
    }
