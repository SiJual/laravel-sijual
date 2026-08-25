"""EmbeddingPort over the OpenAI embeddings API (REST).

text-embedding-3 models accept a ``dimensions`` parameter, so the stored
pgvector dimension (768) is preserved without a migration.
"""

from __future__ import annotations

import httpx

from sipromo.infrastructure.ai.openai_compatible_adapter import _map_provider_error

_BASE_URL = "https://api.openai.com/v1"


class OpenAIEmbeddingAdapter:
    """EmbeddingPort over OpenAI embeddings (REST API)."""

    def __init__(
        self,
        *,
        api_key: str,
        model: str = "text-embedding-3-small",
        dimension: int = 768,
        timeout_ms: int = 30_000,
        base_url: str = _BASE_URL,
    ) -> None:
        self._model = model
        self._dimension = dimension
        self._base_url = base_url.rstrip("/")
        self._client = httpx.AsyncClient(
            timeout=timeout_ms / 1000.0,
            headers={"Authorization": f"Bearer {api_key}"},
        )

    @property
    def dimension(self) -> int:
        return self._dimension

    async def embed_documents(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        try:
            resp = await self._client.post(
                f"{self._base_url}/embeddings",
                json={
                    "model": self._model,
                    "input": texts,
                    "dimensions": self._dimension,
                },
            )
            resp.raise_for_status()
            data = resp.json()
        except Exception as exc:
            raise _map_provider_error(exc) from exc
        return [list(e.get("embedding", [])) for e in data.get("data", [])]

    async def embed_query(self, text: str) -> list[float]:
        embeddings = await self.embed_documents([text])
        if not embeddings or not embeddings[0]:
            raise RuntimeError("embedding provider returned no embeddings")
        return embeddings[0]
