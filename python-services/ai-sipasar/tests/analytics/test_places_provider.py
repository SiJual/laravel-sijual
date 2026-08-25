"""Contract tests for Google Places (New) and OSM Overpass providers."""

from __future__ import annotations

import json

import httpx
import pytest

from sipasar.providers.places_provider import (
    GooglePlacesProvider,
    OSMOverpassProvider,
    PlacesProviderUnavailableError,
)


@pytest.mark.asyncio
async def test_google_places_new_request_and_radius_filter() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.method == "POST"
        assert request.url.path == "/v1/places:searchNearby"
        assert request.headers["X-Goog-Api-Key"] == "test-google-key"
        assert "places.displayName" in request.headers["X-Goog-FieldMask"]
        payload = json.loads(request.content)
        assert payload["includedTypes"] == ["cafe", "coffee_shop"]
        assert payload["locationRestriction"]["circle"]["radius"] == 1000.0
        return httpx.Response(
            200,
            json={
                "places": [
                    {
                        "id": "near-cafe",
                        "displayName": {"text": "Kopi Dekat"},
                        "formattedAddress": "Jl. Dekat",
                        "location": {"latitude": -7.967, "longitude": 112.633},
                        "primaryType": "cafe",
                        "rating": 4.6,
                        "userRatingCount": 120,
                        "googleMapsUri": "https://maps.google.com/?cid=near",
                    },
                    {
                        "id": "far-cafe",
                        "displayName": {"text": "Kopi Jauh"},
                        "location": {"latitude": -8.1, "longitude": 112.8},
                    },
                ]
            },
        )

    client = httpx.AsyncClient(transport=httpx.MockTransport(handler))
    provider = GooglePlacesProvider(client=client)
    provider.settings.GOOGLE_PLACES_API_KEY = "test-google-key"

    results = await provider.nearby_search(-7.9666, 112.6326, 1000, "kuliner_kopi")

    assert len(results) == 1
    assert results[0]["name"] == "Kopi Dekat"
    assert results[0]["source"] == "google_places"
    assert results[0]["distance_meters"] <= 1000
    await client.aclose()


@pytest.mark.asyncio
async def test_overpass_get_query_parses_named_places_and_filters_radius() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.method == "GET"
        assert "data" in request.url.params
        assert "node" in request.url.params["data"]
        assert request.headers["User-Agent"].startswith("SiPasar-Analytics/")
        return httpx.Response(
            200,
            json={
                "elements": [
                    {
                        "type": "node",
                        "id": 11,
                        "lat": -7.967,
                        "lon": 112.633,
                        "tags": {
                            "name": "Kopi OSM",
                            "amenity": "cafe",
                            "addr:street": "Jalan Veteran",
                        },
                    },
                    {
                        "type": "node",
                        "id": 12,
                        "lat": -7.9671,
                        "lon": 112.6331,
                        "tags": {"amenity": "cafe"},
                    },
                    {
                        "type": "node",
                        "id": 13,
                        "lat": -8.1,
                        "lon": 112.8,
                        "tags": {"name": "Kopi di Luar Radius", "amenity": "cafe"},
                    },
                ]
            },
        )

    client = httpx.AsyncClient(transport=httpx.MockTransport(handler))
    provider = OSMOverpassProvider(client=client, urls=("https://overpass.test/api",))

    results = await provider.nearby_search(-7.9666, 112.6326, 1000, "kuliner_kopi")

    assert len(results) == 1
    assert results[0]["name"] == "Kopi OSM"
    assert results[0]["place_id"] == "node/11"
    assert results[0]["source"] == "openstreetmap"
    assert results[0]["address"] == "Jalan Veteran"
    await client.aclose()


@pytest.mark.asyncio
async def test_overpass_raises_when_all_endpoints_fail() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(406, text="Not Acceptable")

    client = httpx.AsyncClient(transport=httpx.MockTransport(handler))
    provider = OSMOverpassProvider(
        client=client,
        urls=("https://one.test/api", "https://two.test/api"),
    )

    with pytest.raises(PlacesProviderUnavailableError, match="All Overpass endpoints failed"):
        await provider.nearby_search(-7.9666, 112.6326, 1000, "kuliner_kopi")
    await client.aclose()
