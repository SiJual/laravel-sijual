"""SiPasar — Geocoding provider: address ↔ coordinates.

Primary: Google Geocoding API.
Fallback: Nominatim (OpenStreetMap).
"""

from __future__ import annotations

import logging
from dataclasses import dataclass

import httpx
from tenacity import (
    retry,
    retry_if_exception_type,
    stop_after_attempt,
    wait_exponential,
)

from sipasar.core.config import get_settings

logger = logging.getLogger(__name__)


@dataclass
class GeocodingResult:
    latitude: float
    longitude: float
    formatted_address: str
    source: str


class GeocodingProvider:
    """Geocode an address string to (lat, lon).

    Primary: Google Geocoding API (if key configured).
    Fallback: Nominatim/OSM (free, rate-limited to 1 req/s).
    """

    GOOGLE_URL = "https://maps.googleapis.com/maps/api/geocode/json"
    NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"

    def __init__(self) -> None:
        self.settings = get_settings()
        self._client = httpx.AsyncClient(
            timeout=self.settings.HTTP_TIMEOUT,
            headers={"User-Agent": "SiPasar-Analytics/1.0"},
        )

    async def aclose(self) -> None:
        await self._client.aclose()

    @retry(
        retry=retry_if_exception_type((httpx.TransportError, httpx.TimeoutException)),
        wait=wait_exponential(multiplier=1, min=1, max=8),
        stop=stop_after_attempt(3),
    )
    async def geocode(self, address: str) -> GeocodingResult | None:
        """Convert an address string to coordinates."""
        if self.settings.GOOGLE_GEOCODING_API_KEY:
            result = await self._geocode_google(address)
            if result:
                return result
            logger.info("Google Geocoding returned no result; trying Nominatim.")

        return await self._geocode_nominatim(address)

    async def _geocode_google(self, address: str) -> GeocodingResult | None:
        params = {
            "address": address,
            "key": self.settings.GOOGLE_GEOCODING_API_KEY,
            "region": "id",  # bias to Indonesia
        }
        resp = await self._client.get(self.GOOGLE_URL, params=params)
        resp.raise_for_status()
        data = resp.json()

        if data.get("status") != "OK" or not data.get("results"):
            return None

        first = data["results"][0]
        loc = first["geometry"]["location"]
        return GeocodingResult(
            latitude=loc["lat"],
            longitude=loc["lng"],
            formatted_address=first.get("formatted_address", address),
            source="google_geocoding",
        )

    async def _geocode_nominatim(self, address: str) -> GeocodingResult | None:
        params = {
            "q": address,
            "format": "json",
            "limit": 1,
            "countrycodes": "id",
        }
        resp = await self._client.get(self.NOMINATIM_URL, params=params)
        resp.raise_for_status()
        data = resp.json()

        if not data:
            return None

        first = data[0]
        return GeocodingResult(
            latitude=float(first["lat"]),
            longitude=float(first["lon"]),
            formatted_address=first.get("display_name", address),
            source="nominatim",
        )

    @retry(
        retry=retry_if_exception_type((httpx.TransportError, httpx.TimeoutException)),
        wait=wait_exponential(multiplier=1, min=1, max=8),
        stop=stop_after_attempt(3),
    )
    async def reverse_geocode(self, lat: float, lon: float) -> str:
        """Convert coordinates to an address string (best effort)."""
        if self.settings.GOOGLE_GEOCODING_API_KEY:
            params = {
                "latlng": f"{lat},{lon}",
                "key": self.settings.GOOGLE_GEOCODING_API_KEY,
            }
            resp = await self._client.get(self.GOOGLE_URL, params=params)
            resp.raise_for_status()
            data = resp.json()
            if data.get("status") == "OK" and data.get("results"):
                return data["results"][0].get("formatted_address", "")

        # Nominatim reverse
        params = {"lat": lat, "lon": lon, "format": "json"}
        resp = await self._client.get(
            "https://nominatim.openstreetmap.org/reverse", params=params
        )
        resp.raise_for_status()
        data = resp.json()
        return data.get("display_name", "")
