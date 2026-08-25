"""Nearby competitor search through Google Places (New) and OSM Overpass."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

import httpx
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from sipasar.core.config import get_settings
from sipasar.utils.geo_utils import haversine_distance

logger = logging.getLogger(__name__)

PlaceResult = dict[str, Any]

_category_mapping: dict[str, Any] | None = None


class PlacesProviderUnavailableError(RuntimeError):
    """Raised when every configured nearby-search provider is unavailable."""


def _load_category_mapping() -> dict[str, Any]:
    global _category_mapping
    if _category_mapping is None:
        mapping_path = Path(get_settings().CATEGORY_MAPPING_PATH)
        if mapping_path.exists():
            with mapping_path.open(encoding="utf-8") as file:
                _category_mapping = json.load(file)
        else:
            logger.error("Category mapping not found at %s", mapping_path)
            _category_mapping = {}
    return _category_mapping


def _category_data(category: str) -> dict[str, Any]:
    mapping = _load_category_mapping()
    return mapping.get(category) or mapping.get("default", {})


def _get_google_types(category: str) -> list[str]:
    return _category_data(category).get("google_places_types", [])


def _get_osm_tag_sets(category: str) -> list[dict[str, str]]:
    data = _category_data(category)
    tag_sets = data.get("osm_tag_sets")
    if tag_sets:
        return tag_sets
    tags = data.get("osm_tags", {})
    return [tags] if tags else [{"amenity": "*"}, {"shop": "*"}]


def _coordinates(lat: Any, lon: Any) -> tuple[float, float] | None:
    if not isinstance(lat, int | float) or not isinstance(lon, int | float):
        return None
    latitude, longitude = float(lat), float(lon)
    if not (-90 <= latitude <= 90 and -180 <= longitude <= 180):
        return None
    return latitude, longitude


def _deduplicate_and_filter(places: list[PlaceResult], radius_meters: int) -> list[PlaceResult]:
    """Keep named places in the requested circle and remove provider duplicates."""
    unique: dict[tuple[Any, ...], PlaceResult] = {}
    for place in places:
        name = str(place.get("name") or "").strip()
        distance = place.get("distance_meters")
        if not name or not isinstance(distance, int | float) or distance > radius_meters:
            continue
        place_id = str(place.get("place_id") or "")
        key: tuple[Any, ...]
        if place_id:
            key = (str(place.get("source") or ""), place_id)
        else:
            key = (
                name.casefold(),
                round(float(place.get("latitude") or 0), 5),
                round(float(place.get("longitude") or 0), 5),
            )
        current = unique.get(key)
        if current is None or float(distance) < float(current["distance_meters"]):
            unique[key] = place
    return sorted(unique.values(), key=lambda item: float(item["distance_meters"]))


class GooglePlacesProvider:
    """Google Places API (New) Nearby Search provider."""

    BASE_URL = "https://places.googleapis.com/v1/places:searchNearby"
    FIELD_MASK = ",".join(
        (
            "places.id",
            "places.displayName",
            "places.formattedAddress",
            "places.location",
            "places.primaryType",
            "places.rating",
            "places.userRatingCount",
            "places.googleMapsUri",
            "places.businessStatus",
        )
    )

    def __init__(self, client: httpx.AsyncClient | None = None) -> None:
        self.settings = get_settings()
        self._owns_client = client is None
        self._client = client or httpx.AsyncClient(timeout=self.settings.HTTP_TIMEOUT)

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    @retry(
        retry=retry_if_exception_type((httpx.TransportError, httpx.TimeoutException)),
        wait=wait_exponential(multiplier=1, min=1, max=8),
        stop=stop_after_attempt(3),
        reraise=True,
    )
    async def nearby_search(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
        category: str,
    ) -> list[PlaceResult]:
        api_key = self.settings.GOOGLE_PLACES_API_KEY
        if not api_key:
            raise PlacesProviderUnavailableError("Google Places API key is not configured")

        place_types = _get_google_types(category)
        payload: dict[str, Any] = {
            "locationRestriction": {
                "circle": {
                    "center": {"latitude": lat, "longitude": lon},
                    "radius": float(radius_meters),
                }
            },
            "maxResultCount": 20,
            "rankPreference": "DISTANCE",
            "languageCode": "id",
            "regionCode": "ID",
        }
        if place_types:
            payload["includedTypes"] = place_types[:50]

        response = await self._client.post(
            self.BASE_URL,
            json=payload,
            headers={
                "X-Goog-Api-Key": api_key,
                "X-Goog-FieldMask": self.FIELD_MASK,
                "Content-Type": "application/json",
            },
        )
        response.raise_for_status()
        data = response.json()

        results: list[PlaceResult] = []
        for place in data.get("places", []):
            location = place.get("location") or {}
            item_lat = location.get("latitude")
            item_lon = location.get("longitude")
            coordinates = _coordinates(item_lat, item_lon)
            if coordinates is None:
                continue
            item_latitude, item_longitude = coordinates
            distance = haversine_distance(lat, lon, item_latitude, item_longitude)
            results.append(
                {
                    "place_id": place.get("id", ""),
                    "name": (place.get("displayName") or {}).get("text", ""),
                    "category": category,
                    "provider_category": place.get("primaryType", ""),
                    "latitude": item_latitude,
                    "longitude": item_longitude,
                    "rating": place.get("rating"),
                    "review_count": place.get("userRatingCount", 0),
                    "address": place.get("formattedAddress", ""),
                    "distance_meters": distance,
                    "source": "google_places",
                    "maps_uri": place.get("googleMapsUri", ""),
                    "business_status": place.get("businessStatus", ""),
                }
            )

        filtered = _deduplicate_and_filter(results, radius_meters)
        logger.info("Google Places returned %d nearby results for '%s'", len(filtered), category)
        return filtered


class OSMOverpassProvider:
    """OpenStreetMap Overpass provider with a secondary public endpoint."""

    DEFAULT_URLS = (
        "https://overpass-api.de/api/interpreter",
        "https://overpass.kumi.systems/api/interpreter",
    )

    def __init__(
        self,
        client: httpx.AsyncClient | None = None,
        urls: tuple[str, ...] | None = None,
    ) -> None:
        self.settings = get_settings()
        self._urls = urls or self.DEFAULT_URLS
        self._owns_client = client is None
        self._client = client or httpx.AsyncClient(timeout=self.settings.HTTP_TIMEOUT * 3)

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    def _build_query(self, lat: float, lon: float, radius_meters: int, category: str) -> str:
        selectors: list[str] = []
        for tags in _get_osm_tag_sets(category):
            filters = "".join(
                f'["{key}"]' if value == "*" else f'["{key}"="{value}"]'
                for key, value in tags.items()
            )
            for element_type in ("node", "way", "relation"):
                selectors.append(f"  {element_type}{filters}(around:{radius_meters},{lat},{lon});")
        return "\n".join(
            (
                "[out:json][timeout:20];",
                "(",
                *selectors,
                ");",
                "out center tags;",
            )
        )

    async def nearby_search(
        self,
        lat: float,
        lon: float,
        radius_meters: int,
        category: str,
    ) -> list[PlaceResult]:
        query = self._build_query(lat, lon, radius_meters, category)
        errors: list[str] = []
        response: httpx.Response | None = None
        for url in self._urls:
            try:
                candidate = await self._client.get(
                    url,
                    params={"data": query},
                    headers={
                        "Accept": "application/json",
                        "User-Agent": "SiPasar-Analytics/1.0 competitor-search",
                    },
                )
                if candidate.is_success:
                    response = candidate
                    break
                errors.append(f"{url}: HTTP {candidate.status_code}")
                logger.warning("Overpass endpoint %s returned %d", url, candidate.status_code)
            except (httpx.TransportError, httpx.TimeoutException) as exc:
                errors.append(f"{url}: {type(exc).__name__}")
                logger.warning("Overpass endpoint %s failed: %s", url, type(exc).__name__)

        if response is None:
            raise PlacesProviderUnavailableError(
                "All Overpass endpoints failed: " + "; ".join(errors)
            )

        results: list[PlaceResult] = []
        for element in response.json().get("elements", []):
            tags = element.get("tags") or {}
            center = element.get("center") or {}
            item_lat = element.get("lat", center.get("lat"))
            item_lon = element.get("lon", center.get("lon"))
            coordinates = _coordinates(item_lat, item_lon)
            if coordinates is None:
                continue
            item_latitude, item_longitude = coordinates
            distance = haversine_distance(lat, lon, item_latitude, item_longitude)
            osm_type = element.get("type", "")
            osm_id = element.get("id", "")
            results.append(
                {
                    "place_id": f"{osm_type}/{osm_id}",
                    "name": tags.get("name", ""),
                    "category": category,
                    "provider_category": tags.get("amenity") or tags.get("shop") or "",
                    "latitude": item_latitude,
                    "longitude": item_longitude,
                    "rating": None,
                    "review_count": 0,
                    "address": _osm_address(tags),
                    "distance_meters": distance,
                    "source": "openstreetmap",
                    "maps_uri": f"https://www.openstreetmap.org/{osm_type}/{osm_id}",
                    "business_status": "OPERATIONAL",
                }
            )

        filtered = _deduplicate_and_filter(results, radius_meters)
        logger.info("OSM returned %d nearby results for '%s'", len(filtered), category)
        return filtered


def _osm_address(tags: dict[str, Any]) -> str:
    if tags.get("addr:full"):
        return str(tags["addr:full"])
    parts = [
        tags.get("addr:housenumber"),
        tags.get("addr:street"),
        tags.get("addr:city"),
    ]
    return " ".join(str(part) for part in parts if part)


class PlacesProvider:
    """Google-first provider chain with OSM fallback and explicit failure semantics."""

    def __init__(
        self,
        google: GooglePlacesProvider | None = None,
        osm: OSMOverpassProvider | None = None,
    ) -> None:
        self._google = google or GooglePlacesProvider()
        self._osm = osm or OSMOverpassProvider()
        self.last_source = ""

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
        google_error: Exception | None = None
        if settings.GOOGLE_PLACES_API_KEY:
            try:
                results = await self._google.nearby_search(lat, lon, radius_meters, category)
                if results:
                    self.last_source = "google_places"
                    return results
                logger.info("Google Places found no results; checking OSM fallback")
            except (httpx.HTTPError, PlacesProviderUnavailableError) as exc:
                google_error = exc
                logger.warning("Google Places unavailable; falling back to OSM: %s", exc)

        try:
            results = await self._osm.nearby_search(lat, lon, radius_meters, category)
            self.last_source = "openstreetmap"
            return results
        except PlacesProviderUnavailableError as osm_error:
            if google_error:
                raise PlacesProviderUnavailableError(
                    f"Google Places and OSM unavailable: {google_error}; {osm_error}"
                ) from osm_error
            raise
