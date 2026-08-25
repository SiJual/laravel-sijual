"""Hybrid retrieval: vector (pgvector) + full-text + RRF fusion + diversity +
metadata filters + minimum relevance threshold + token budget (section 12.4).
Reranking uses the deterministic weighted score from section 12.5.
"""

from __future__ import annotations

import logging
from collections import defaultdict

from sipromo.application.ports.embeddings import EmbeddingPort
from sipromo.application.ports.repositories import (
    KnowledgeReadRepository,
    RetrievedRow,
)
from sipromo.application.ports.retriever import (
    RetrievalQuery,
    RetrievedChunk,
    RetrieverPort,
)

logger = logging.getLogger(__name__)

VECTOR_WEIGHT = 0.65
LEXICAL_WEIGHT = 0.25
METADATA_PRIORITY_WEIGHT = 0.10
RRF_K = 60

# Metadata priority: brand guide > campaign example > policy > faq > catalog > other.
TYPE_PRIORITY = {
    "brand_guide": 1.0,
    "campaign_example": 0.9,
    "policy": 0.8,
    "faq": 0.7,
    "product_catalog": 0.6,
    "other": 0.3,
}


def _rrf_score(rank: int) -> float:
    return 1.0 / (RRF_K + rank)


class HybridRetriever(RetrieverPort):
    def __init__(
        self,
        *,
        embeddings: EmbeddingPort,
        knowledge_repo: KnowledgeReadRepository,
    ) -> None:
        self._embeddings = embeddings
        self._knowledge_repo = knowledge_repo

    async def retrieve(self, query: RetrievalQuery) -> list[RetrievedChunk]:
        from uuid import UUID

        # 1. Query embedding from the sanitized brief.
        query_embedding = await self._embeddings.embed_query(query.query)
        types = query.document_types or None
        umkm_uuid = UUID(query.umkm_id)

        # 2+3. Vector and lexical retrieval.
        vector_rows = await self._knowledge_repo.vector_search(
            umkm_uuid, query_embedding, query.top_k_vector, document_types=types
        )
        lexical_rows = await self._knowledge_repo.lexical_search(
            umkm_uuid, query.query, query.top_k_lexical, document_types=types
        )

        # 4. Reciprocal Rank Fusion (keeps per-signal scores for reranking).
        fused = self._rrf(vector_rows, lexical_rows)

        # 5+6. Metadata priority + minimum relevance threshold.
        scored: list[RetrievedRow] = []
        for triple in fused:
            row = self._rerank(triple, query.min_score)
            if row is not None:
                scored.append(row)

        # 7. Diversity: at most 3 chunks per document.
        diverse = self._diversify(scored, max_per_document=3)

        # 8. Token budget.
        budgeted = self._apply_token_budget(diverse, query.max_context_tokens)

        # 9. Map to the port contract (chunk ids as strings).
        result = [
            RetrievedChunk(
                chunk_id=str(row.chunk_id),
                document_id=str(row.document_id),
                umkm_id=str(umkm_uuid),
                document_type=row.document_type,
                content=row.content,
                metadata=row.metadata,
                score=row.score,
            )
            for row in budgeted
        ]
        logger.info(
            "retrieval",
            extra={
                "umkm_id": query.umkm_id[:8],
                "vector": len(vector_rows),
                "lexical": len(lexical_rows),
                "final": len(result),
            },
        )
        return result

    # ------------------------------------------------------------------ #

    def _rrf(
        self, vector_rows: list[RetrievedRow], lexical_rows: list[RetrievedRow]
    ) -> list[tuple[RetrievedRow, float, float]]:
        """Fuse by RRF ranking while keeping each signal's raw score.

        Returns (row, vector_score, lexical_score) where each score is the
        provider's own metric (cosine similarity / ts_rank) or 0.0 if the
        chunk was not returned by that signal.
        """
        combined: dict[str, dict] = {}
        for rank, row in enumerate(vector_rows):
            key = str(row.chunk_id)
            entry = combined.setdefault(
                key, {"row": row, "vector": 0.0, "lexical": 0.0, "rrf": 0.0}
            )
            entry["vector"] = min(1.0, max(0.0, row.score))
            entry["rrf"] += _rrf_score(rank)
        for rank, row in enumerate(lexical_rows):
            key = str(row.chunk_id)
            entry = combined.setdefault(
                key, {"row": row, "vector": 0.0, "lexical": 0.0, "rrf": 0.0}
            )
            entry["lexical"] = min(1.0, max(0.0, row.score))
            entry["rrf"] += _rrf_score(rank)

        triples = sorted(
            combined.values(),
            key=lambda e: e["rrf"],
            reverse=True,
        )
        return [(e["row"], e["vector"], e["lexical"]) for e in triples]

    def _rerank(
        self, triple: tuple[RetrievedRow, float, float], min_score: float
    ) -> RetrievedRow | None:
        row, vector_score, lexical_score = triple
        vector_score = min(vector_score, 1.0)
        lexical_score = min(lexical_score, 1.0)
        priority = TYPE_PRIORITY.get(row.document_type, 0.3)
        final = (
            VECTOR_WEIGHT * vector_score
            + LEXICAL_WEIGHT * lexical_score
            + METADATA_PRIORITY_WEIGHT * priority
        )
        if final < min_score:
            return None
        row.score = round(final, 4)
        return row

    @staticmethod
    def _diversify(rows: list[RetrievedRow], max_per_document: int = 3) -> list[RetrievedRow]:
        counts: dict[str, int] = defaultdict(int)
        out: list[RetrievedRow] = []
        for row in rows:
            doc_key = str(row.document_id)
            if counts[doc_key] >= max_per_document:
                continue
            counts[doc_key] += 1
            out.append(row)
        return out

    @staticmethod
    def _apply_token_budget(rows: list[RetrievedRow], max_tokens: int) -> list[RetrievedRow]:
        budget = max_tokens
        out: list[RetrievedRow] = []
        for row in rows:
            approx_tokens = max(1, len(row.content) // 4)
            if approx_tokens > budget:
                break
            budget -= approx_tokens
            out.append(row)
        return out
