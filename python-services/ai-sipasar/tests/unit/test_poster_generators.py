"""Unit tests for the AI poster generation (OpenAI image models, REST).

The image model draws the COMPLETE poster: the poster copy (headline,
message, CTA, hashtags) is designed directly into the image by the model.
Real product photos are attached as visual references when available.
These tests never touch the network.
"""

from __future__ import annotations

import json
from typing import Any
from unittest.mock import Mock, patch

import pytest

from sipromo.application.ports.poster_generator import (
    PosterGenerationError,
    PosterSpec,
    ProductMedia,
)
from sipromo.infrastructure.visual.openai_image_poster import (
    IMAGES_URL,
    RESPONSES_URL,
    OpenAIImagePosterGenerator,
    _build_prompt,
)

# ---------------------------------------------------------------- #
# fixtures
# ---------------------------------------------------------------- #


def _spec(**overrides: Any) -> PosterSpec:
    defaults: dict[str, Any] = {
        "brand_name": "Kopdes",
        "headline": "Promo Akhir Bulan",
        "message": "Coba Keripik Pedas 100g.",
        "product_media": [],
        "call_to_action": "Beli sekarang",
        "hashtags": ["#Kopdes"],
        "tone": "friendly",
    }
    defaults.update(overrides)
    return PosterSpec(**defaults)


# ---------------------------------------------------------------- #
# prompt building
# ---------------------------------------------------------------- #


def test_prompt_includes_brand_and_copy() -> None:
    prompt = _build_prompt(_spec())

    assert "Kopdes" in prompt
    assert "Promo Akhir Bulan" in prompt
    assert "Keripik Pedas 100g" in prompt
    assert "Beli sekarang" in prompt
    assert "#Kopdes" in prompt
    assert "bahasa Indonesia" in prompt
    assert "Instagram persegi (1080x1080)" in prompt


def test_prompt_lists_products_and_keeps_photos() -> None:
    spec = _spec(
        product_media=[
            ProductMedia(name="Keripik", image_bytes=b"abc"),
        ]
    )
    prompt = _build_prompt(spec)

    assert "PRODUK yang ditampilkan: Keripik." in prompt
    assert "Gunakan foto produk yang saya lampirkan apa adanya, jangan ubah produknya." in prompt
    assert "VISUAL PRODUK" not in prompt


def test_prompt_has_cta_and_hashtags_sections() -> None:
    prompt = _build_prompt(_spec())

    assert "TOMBOL/TEKS AJAKAN: 'Beli sekarang'" in prompt
    assert "HASHTAG (di bagian bawah): #Kopdes" in prompt
    assert "tanpa watermark" in prompt


def test_prompt_draws_products_from_description_when_no_photos() -> None:
    spec = _spec(
        product_media=[
            ProductMedia(
                name="Keripik",
                description="Keripik pisang pedas renyah",
                image_bytes=None,
            ),
        ]
    )
    prompt = _build_prompt(spec)

    assert "PRODUK yang ditampilkan: Keripik." in prompt
    assert "VISUAL PRODUK" in prompt
    assert "Keripik pisang pedas renyah" in prompt
    assert "TANPA orang" in prompt
    assert "Gunakan foto produk yang saya lampirkan" not in prompt


def test_prompt_uses_attached_photo_when_available() -> None:
    spec = _spec(
        product_media=[
            ProductMedia(name="Keripik", description="Keripik pisang", image_bytes=b"abc")
        ]
    )
    prompt = _build_prompt(spec)

    assert "Gunakan foto produk yang saya lampirkan apa adanya" in prompt
    assert "VISUAL PRODUK" not in prompt


# ---------------------------------------------------------------- #
# payloads
# ---------------------------------------------------------------- #


def test_payload_responses_without_photo() -> None:
    generator = OpenAIImagePosterGenerator(api_key="test-key")

    endpoint, payload = generator._payload(_spec())

    assert endpoint == RESPONSES_URL
    assert payload["model"] == "gpt-5.6"
    assert payload["tools"] == [{"type": "image_generation"}]
    content = payload["input"][0]["content"]
    assert content[0]["type"] == "input_text"
    assert "Kopdes" in content[0]["text"]
    assert not any(p["type"] == "input_image" for p in content)


def test_payload_responses_with_photo_reference() -> None:
    generator = OpenAIImagePosterGenerator(api_key="test-key")
    spec = _spec(
        product_media=[
            ProductMedia(
                name="Keripik",
                description="Keripik pisang",
                image_bytes=b"\x89PNG\r\n\x1a\n",
            )
        ]
    )

    endpoint, payload = generator._payload(spec)

    assert endpoint == RESPONSES_URL
    assert payload["model"] == "gpt-5.6"
    assert payload["tools"] == [{"type": "image_generation"}]
    assert "input_image" in json.dumps(payload)
    assert "Gunakan foto produk yang saya lampirkan" in payload["input"][0]["content"][0]["text"]


def test_payload_embeds_image_as_base64_data_url() -> None:
    generator = OpenAIImagePosterGenerator(api_key="test-key")
    spec = _spec(
        product_media=[
            ProductMedia(
                name="Keripik",
                description="Keripik pisang",
                image_bytes=b"\x89PNG\r\n\x1a\n",
            )
        ]
    )

    _, payload = generator._payload(spec)

    part = payload["input"][0]["content"][1]
    assert part["type"] == "input_image"
    assert part["image_url"].startswith("data:image/png;base64,")


# ---------------------------------------------------------------- #
# generate()
# ---------------------------------------------------------------- #


def test_generate_returns_png_bytes() -> None:
    import base64

    generator = OpenAIImagePosterGenerator(api_key="test-key")
    expected = b"\x89PNG\r\n\x1a\n" + b"0" * 64

    class FakeResponse:
        status_code = 200

        def json(self) -> dict[str, Any]:
            return {
                "output": [
                    {
                        "type": "image_generation_call",
                        "result": base64.b64encode(expected).decode("ascii"),
                    }
                ]
            }

    class FakeClient:
        def __enter__(self) -> FakeClient:
            return self

        def __exit__(self, *args: Any) -> None:
            return None

        def post(self, *args: Any, **kwargs: Any) -> FakeResponse:
            return FakeResponse()

    with patch(
        "sipromo.infrastructure.visual.openai_image_poster.httpx.Client",
        return_value=FakeClient(),
    ):
        assert generator.generate(_spec()) == expected


def test_generate_raises_on_http_error() -> None:
    generator = OpenAIImagePosterGenerator(api_key="test-key")

    class FakeResponse:
        status_code = 500

        def json(self) -> dict[str, Any]:
            return {"error": "boom"}

    class FakeClient:
        def __enter__(self) -> FakeClient:
            return self

        def __exit__(self, *args: Any) -> None:
            return None

        def post(self, *args: Any, **kwargs: Any) -> FakeResponse:
            return FakeResponse()

    with (
        patch(
            "sipromo.infrastructure.visual.openai_image_poster.httpx.Client",
            return_value=FakeClient(),
        ),
        pytest.raises(PosterGenerationError),
    ):
        generator.generate(_spec())


def test_generate_raises_when_no_image() -> None:
    generator = OpenAIImagePosterGenerator(api_key="test-key")

    class FakeResponse:
        status_code = 200

        def json(self) -> dict[str, Any]:
            return {"output": []}

    class FakeClient:
        def __enter__(self) -> FakeClient:
            return self

        def __exit__(self, *args: Any) -> None:
            return None

        def post(self, *args: Any, **kwargs: Any) -> FakeResponse:
            return FakeResponse()

    with (
        patch(
            "sipromo.infrastructure.visual.openai_image_poster.httpx.Client",
            return_value=FakeClient(),
        ),
        pytest.raises(PosterGenerationError),
    ):
        generator.generate(_spec())


def test_generate_uses_httpx_client() -> None:
    from unittest.mock import MagicMock

    generator = OpenAIImagePosterGenerator(api_key="test-key")
    fake = Mock()
    fake.status_code = 200
    fake.json.return_value = {"output": []}
    client = MagicMock()
    client.post.return_value = fake
    client.__enter__.return_value = client
    client.__exit__.return_value = None

    with (
        patch(
            "sipromo.infrastructure.visual.openai_image_poster.httpx.Client",
            return_value=client,
        ),
        pytest.raises(PosterGenerationError),
    ):
        generator.generate(_spec())
    client.post.assert_called_once()


# ---------------------------------------------------------------- #
# extraction
# ---------------------------------------------------------------- #


def test_extract_image_from_plain_response() -> None:
    import base64

    payload = {
        "data": [
            {"b64_json": base64.b64encode(b"\x89PNG").decode("ascii")},
        ]
    }

    assert OpenAIImagePosterGenerator._extract_image(IMAGES_URL, payload) == b"\x89PNG"


def test_extract_image_from_responses_output() -> None:
    import base64

    payload = {
        "output": [
            {
                "type": "image_generation_call",
                "result": base64.b64encode(b"\x89PNG").decode("ascii"),
            }
        ]
    }

    assert OpenAIImagePosterGenerator._extract_image(RESPONSES_URL, payload) == b"\x89PNG"


def test_extract_image_ignores_malformed() -> None:
    assert OpenAIImagePosterGenerator._extract_image(IMAGES_URL, {}) is None
    assert OpenAIImagePosterGenerator._extract_image(IMAGES_URL, {"data": []}) is None
    assert OpenAIImagePosterGenerator._extract_image(RESPONSES_URL, {"output": []}) is None
    malformed = {"output": [{"type": "message"}]}
    assert OpenAIImagePosterGenerator._extract_image(RESPONSES_URL, malformed) is None
