"""SiPasar — Geospatial utility functions."""

from __future__ import annotations

import math

# ── Constants ─────────────────────────────────────────────────────────────────

EARTH_RADIUS_KM = 6371.0


# ── Distance ──────────────────────────────────────────────────────────────────


def haversine_distance(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """
    Calculate the great-circle distance (in meters) between two points on Earth.

    Uses the Haversine formula.

    Args:
        lat1, lon1: Coordinates of point 1 (decimal degrees).
        lat2, lon2: Coordinates of point 2 (decimal degrees).

    Returns:
        Distance in meters.
    """
    lat1_r = math.radians(lat1)
    lat2_r = math.radians(lat2)
    dlat = math.radians(lat2 - lat1)
    dlon = math.radians(lon2 - lon1)

    a = math.sin(dlat / 2) ** 2 + math.cos(lat1_r) * math.cos(lat2_r) * math.sin(dlon / 2) ** 2
    c = 2 * math.asin(math.sqrt(a))
    return EARTH_RADIUS_KM * c * 1000  # meters


def bounding_box(lat: float, lon: float, radius_meters: float) -> dict[str, float]:
    """
    Calculate a bounding box around a center point for a given radius.

    Returns:
        Dict with keys: lat_min, lat_max, lon_min, lon_max.
    """
    radius_km = radius_meters / 1000.0
    lat_delta = math.degrees(radius_km / EARTH_RADIUS_KM)
    lon_delta = math.degrees(radius_km / (EARTH_RADIUS_KM * math.cos(math.radians(lat))))
    return {
        "lat_min": lat - lat_delta,
        "lat_max": lat + lat_delta,
        "lon_min": lon - lon_delta,
        "lon_max": lon + lon_delta,
    }


# ── Normalisation ─────────────────────────────────────────────────────────────


def normalize_to_01(value: float, min_val: float, max_val: float) -> float:
    """
    Normalize a value to the [0, 1] range using min-max scaling.

    If min == max, returns 0.5 (mid-range) to avoid division by zero.
    """
    if max_val == min_val:
        return 0.5
    return max(0.0, min(1.0, (value - min_val) / (max_val - min_val)))


def clamp(value: float, lo: float = 0.0, hi: float = 1.0) -> float:
    """Clamp value to [lo, hi]."""
    return max(lo, min(hi, value))


# ── Area ──────────────────────────────────────────────────────────────────────


def circle_area_km2(radius_meters: float) -> float:
    """Area of a circle in km² given radius in meters."""
    radius_km = radius_meters / 1000.0
    return math.pi * radius_km**2


# ── Competition density ────────────────────────────────────────────────────────


def competition_density(competitor_count: int, radius_meters: float) -> float:
    """
    Competitors per km² within the given radius.

    PRD §9.1: competition_density = jumlah_kompetitor / (π × radius_km²)
    """
    area = circle_area_km2(radius_meters)
    if area == 0:
        return 0.0
    return competitor_count / area
