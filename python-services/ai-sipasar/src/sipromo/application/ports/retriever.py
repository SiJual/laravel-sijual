"""Retrieval port for the RAG pipeline."""

from __future__ import annotations

from typing import Protocol

from pydantic import BaseModel, Field

from sipromo.domain.value_objects.content_type import DocumentType


class RetrievalQuery(BaseModel):
    query: str = Field(min_length=1, max_length=2000)
    umkm_id: str
    document_types: list[DocumentType] = Field(default_factory=list)
    top_k_vector: int = 12
    top_k_lexical: int = 12
    final_k: int = 8
    min_score: float = 0.55
    max_context_tokens: int = 6000


class RetrievedChunk(BaseModel):
    chunk_id: str
    document_id: str
    umkm_id: str
    document_type: str
    content: str
    metadata: dict = Field(default_factory=dict)
    score: float


class RetrieverPort(Protocol):
    async def retrieve(self, query: RetrievalQuery) -> list[RetrievedChunk]: ...
