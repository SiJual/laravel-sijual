"""AI poster generation via OpenAI image models (REST).

The image model draws the COMPLETE poster: the poster copy (headline,
message, CTA, hashtags) and product visuals are designed directly into the
image. When real product photos are attached (Responses API path), the model
redraws the products from those photos; when no photo is available, the model
draws the product visual from its description.

Two REST paths, same ``PosterGeneratorPort`` contract:
- no product photos: plain ``/v1/images/generations`` (gpt-image-1);
- with product photos: Responses API (gpt-5.6 + image_generation tool),
  which is the only OpenAI surface that accepts reference images.
"""

from __future__ import annotations

import base64
import logging
from typing import Any

import httpx

from sipromo.application.ports.poster_generator import (
    PosterGenerationError,
    PosterSpec,
)

logger = logging.getLogger(__name__)

_TONE_STYLE = {
    "friendly": "hangat, ceria, warna oranye-krem",
    "professional": "bersih, elegan, warna biru tua",
    "playful": "energik, warna ungu-merah muda",
    "premium": "mewah, gelap dengan aksen emas",
    "educational": "segar, warna hijau, informatif",
}

IMAGES_URL = "https://api.openai.com/v1/images/generations"
RESPONSES_URL = "https://api.openai.com/v1/responses"


class OpenAIImagePosterGenerator:
    """Synchronous REST client for OpenAI image generation (full poster)."""

    def __init__(
        self,
        *,
        api_key: str,
        model: str = "gpt-image-1",
        size: str = "1024x1024",
        quality: str = "medium",
        timeout_seconds: int = 180,
    ) -> None:
        self._api_key = api_key
        self._model = model
        self._size = size
        self._quality = quality
        self._timeout = timeout_seconds

    def generate(self, spec: PosterSpec) -> bytes:
        endpoint, payload = self._payload(spec)
        with httpx.Client(timeout=self._timeout) as client:
            response = client.post(
                endpoint,
                headers={"Authorization": f"Bearer {self._api_key}"},
                json=payload,
            )
        if response.status_code != 200:
            raise PosterGenerationError(f"image model returned HTTP {response.status_code}")
        image = self._extract_image(endpoint, response.json())
        if image is None:
            raise PosterGenerationError("image model returned no image data")
        return image

    def _payload(self, spec: PosterSpec) -> tuple[str, dict[str, Any]]:
        prompt = _build_prompt(spec)
        content: list[dict[str, Any]] = [
            {"type": "input_text", "text": prompt},
            *[
                {
                    "type": "input_image",
                    "image_url": "data:image/png;base64," + base64.b64encode(image).decode("ascii"),
                }
                for image in (m.image_bytes for m in spec.product_media if m.image_bytes)
            ],
        ]
        return RESPONSES_URL, {
            "model": "gpt-5.6",
            "input": [{"role": "user", "content": content}],
            "tools": [{"type": "image_generation"}],
        }

    @staticmethod
    def _extract_image(endpoint: str, data: dict[str, Any]) -> bytes | None:
        if endpoint == IMAGES_URL:
            return _extract_b64_json(data)
        for item in data.get("output", []):
            if item.get("type") == "image_generation_call":
                return _decode_b64(item.get("result"))
        return None


def _build_prompt(spec: PosterSpec) -> str:
    style = _TONE_STYLE.get(spec.tone.strip().lower(), _TONE_STYLE["friendly"])
    products = ", ".join(m.name for m in spec.product_media if m.name.strip())
    lines = [
        f"Buat poster promosi Instagram persegi (1080x1080) untuk brand "
        f"'{spec.brand_name}' dengan gaya {style}.",
        f"HEADLINE (teks besar): '{spec.headline}'",
        f"PESAN: '{spec.message}'",
    ]
    if products:
        lines.append(f"PRODUK yang ditampilkan: {products}.")
    if any(m.image_bytes for m in spec.product_media):
        lines.append("Gunakan foto produk yang saya lampirkan apa adanya, jangan ubah produknya.")
    else:
        lines.append(
            "VISUAL PRODUK: tidak ada foto tersedia, jadi GAMBARLAH sendiri produknya "
            "persis seperti nama dan deskripsi produk berikut, dengan gaya foto "
            "produk profesional (studio, pencahayaan bagus, latar bersih yang "
            "menunjang produk, tampilan menarik):"
        )
        for media in spec.product_media:
            hint = media.description.strip() or media.name.strip()
            if hint:
                lines.append(f"- {media.name}: {hint}")
        lines.append("TANPA orang, TANPA tangan, TANPA objek lain yang tidak relevan.")
    if spec.call_to_action:
        lines.append(f"TOMBOL/TEKS AJAKAN: '{spec.call_to_action}'")
    if spec.hashtags:
        lines.append(f"HASHTAG (di bagian bawah): {' '.join(spec.hashtags)}")
    lines.extend(
        [
            "Aturan: bahasa Indonesia, tata letak rapi, teks tidak terpotong dan "
            "terbaca jelas, tanpa watermark, tanpa teks tambahan di luar yang diminta.",
            "Kembalikan hanya gambar poster (format PNG).",
        ]
    )
    return "\n".join(lines)


def _decode_b64(value: Any) -> bytes | None:
    if not isinstance(value, str):
        return None
    try:
        return base64.b64decode(value)
    except (ValueError, TypeError):
        return None


def _extract_b64_json(data: dict[str, Any]) -> bytes | None:
    try:
        return _decode_b64(data["data"][0]["b64_json"])
    except (KeyError, IndexError, TypeError):
        return None
