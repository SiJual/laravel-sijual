"""SiPasar — Google Places API wrapper with OSM Overpass fallback.

Implements retry logic and transparent provider switching.
"""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

import httpx
from tenacity import (
    retry,
    retry_if_exception_type,
    stop_after_attempt,
    wait_exponential,
)

from sipasar.core.config import get_settings
from sipasar.utils.geo_utils import bounding_box, haversine_distance

logger = logging.getLogger(__name__)

# ── Typing helpers ────────────────────────────────────────────────────────────

PlaceResult = dict[str, Any]


# ── Category mapping loader ────────────────────────────────────────────────────

_category_mapping: dict[str, Any] | None = None


def _load_category_mapping() -> dict[str, Any]:
    global _category_mapping
    if _category_mapping is None:
        mapping_path = Path(get_settings().CATEGORY_MAPPING_PATH)
        if mapping_path.exists():
            with mapping_path.open(encoding="utf-8") as f:
                _category_mapping = json.load(f)
        else:
            _category_mapping = {}
    return _category_mapping


def _get_google_types(category: str) -> list[str]:
    mapping = _load_category_mapping()
    cat_data = mapping.get(category) or mapping.get("default", {})
    return cat_data.get("google_places_types", ["establishment"])


def _get_osm_tags(category: str) -> dict[str, str]:
    mapping = _load_category_mapping()
    cat_data = mapping.get(category) or mapping.get("default", {})
    return cat_data.get("osm_tags", {})


# ── Google Places Provider ────────────────────────────────────────────────────


class GooglePlacesProvider:
    """Wrapper around Google Places Nearby Search API."""

    BASE_URL = "https://maps.googleapis.com/maps/api/place/nearbysearch/json"

    def __init__(self) -> None:
        self.settings = get_settings()
        self._client = httpx.AsyncClient(timeout=self.settings.HTTP_TIMEOUT)

    async def aclose(self) -> None:
        await self._client.aclose()

    @retry(
        retry=retry_if_exception_type((httpx.TransportError, httpx.TimeoutException)),
        wait=wait_exponential(multiplier=1, min=1, max=8),
        stop=stop_after_attempt(3),
    )
    async def nearby_search(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
        category: str,
    ) -> list[PlaceResult]:
        """Query Google Places Nearby Search and return list of place dicts."""
        if not self.settings.GOOGLE_PLACES_API_KEY:
            logger.warning("Google Places API key not set; skipping Google provider.")
            return []

        place_types = _get_google_types(category)
        results: list[PlaceResult] = []

        for place_type in place_types:
            params = {
                "location": f"{lat},{lon}",
                "radius": radius_meters,
                "type": place_type,
                "key": self.settings.GOOGLE_PLACES_API_KEY,
            }
            resp = await self._client.get(self.BASE_URL, params=params)
            resp.raise_for_status()
            data = resp.json()

            if data.get("status") not in ("OK", "ZERO_RESULTS"):
                logger.warning(
                    "Google Places API error: %s — %s",
                    data.get("status"),
                    data.get("error_message", ""),
                )
                continue

            for item in data.get("results", []):
                loc = item.get("geometry", {}).get("location", {})
                item_lat = loc.get("lat", 0.0)
                item_lon = loc.get("lng", 0.0)
                dist = haversine_distance(lat, lon, item_lat, item_lon)
                results.append(
                    {
                        "place_id": item.get("place_id", ""),
                        "name": item.get("name", ""),
                        "category": category,
                        "latitude": item_lat,
                        "longitude": item_lon,
                        "rating": item.get("rating"),
                        "review_count": item.get("user_ratings_total", 0),
                        "address": item.get("vicinity", ""),
                        "distance_meters": dist,
                        "source": "google_places",
                    }
                )

        logger.info("Google Places returned %d results for '%s'", len(results), category)
        return results


# ── OSM Overpass Provider ─────────────────────────────────────────────────────


class OSMOverpassProvider:
    """Wrapper around OpenStreetMap Overpass API (free, no key required)."""

    BASE_URL = "https://overpass-api.de/api/interpreter"

    def __init__(self) -> None:
        self.settings = get_settings()
        self._client = httpx.AsyncClient(timeout=self.settings.HTTP_TIMEOUT * 3)

    async def aclose(self) -> None:
        await self._client.aclose()

    def _build_query(self, lat: float, lon: float, radius_meters: int, category: str) -> str:
        osm_tags = _get_osm_tags(category)
        if not osm_tags:
            # Generic amenity search
            filters = '[amenity]'
        else:
            filters = "".join(f'["{k}"="{v}"]' for k, v in osm_tags.items())

        return f"""
[out:json][timeout:25];
(
  node{filters}(around:{radius_meters},{lat},{lon});
  way{filters}(around:{radius_meters},{lat},{lon});
);
out center;
"""

    @retry(
        retry=retry_if_exception_type((httpx.TransportError, httpx.TimeoutException)),
        wait=wait_exponential(multiplier=2, min=2, max=16),
        stop=stop_after_attempt(3),
    )
    async def nearby_search(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
        category: str,
    ) -> list[PlaceResult]:
        """Query OSM Overpass API and return list of place dicts."""
        from urllib.parse import urlencode  # noqa: PLC0415

        query = self._build_query(lat, lon, radius_meters, category)
        resp = await self._client.post(
            self.BASE_URL,
            content=urlencode({"data": query}).encode("utf-8"),
            headers={
                "Content-Type": "application/x-www-form-urlencoded",
                "Accept": "application/json",
            },
        )
        if not resp.is_success:
            logger.warning(
                "OSM Overpass returned %d for '%s'; returning empty results.",
                resp.status_code,
                category,
            )
            return []
        data = resp.json()

        results: list[PlaceResult] = []
        for element in data.get("elements", []):
            tags = element.get("tags", {})
            # Nodes have lat/lon directly; ways have center
            item_lat = element.get("lat") or element.get("center", {}).get("lat", 0.0)
            item_lon = element.get("lon") or element.get("center", {}).get("lon", 0.0)
            dist = haversine_distance(lat, lon, item_lat, item_lon)
            results.append(
                {
                    "place_id": str(element.get("id", "")),
                    "name": tags.get("name", "Unknown"),
                    "category": category,
                    "latitude": item_lat,
                    "longitude": item_lon,
                    "rating": None,  # OSM doesn't have ratings
                    "review_count": 0,
                    "address": tags.get("addr:full", tags.get("addr:street", "")),
                    "distance_meters": dist,
                    "source": "osm",
                }
            )

        logger.info("OSM returned %d results for '%s'", len(results), category)
        return results


# ── Composite Provider (Google → OSM fallback) ────────────────────────────────


class PlacesProvider:
    """
    Primary: Google Places API.
    Fallback: OSM Overpass if Google is not configured or fails.
    """

    def __init__(self) -> None:
        self._google = GooglePlacesProvider()
        self._osm = OSMOverpassProvider()

    async def aclose(self) -> None:
        await self._google.aclose()
        await self._osm.aclose()

    async def nearby_search(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
        category: str,
    ) -> list[PlaceResult]:
        settings = get_settings()

        if settings.GOOGLE_PLACES_API_KEY:
            try:
                results = await self._google.nearby_search(lat, lon, radius_meters, category)
                if results:
                    return results
                logger.info("Google Places returned 0 results; trying OSM fallback.")
            except Exception as exc:
                logger.warning("Google Places failed (%s); falling back to OSM.", exc)

        return await self._osm.nearby_search(lat, lon, radius_meters, category)
