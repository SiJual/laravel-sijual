"""Knowledge repository over the new additive schema (documents, chunks)."""

from __future__ import annotations

import json
from uuid import UUID

from pgvector import Vector
from sqlalchemy import delete, func, select, text

from sipromo.application.ports.repositories import (
    KnowledgeReadRepository,
    KnowledgeWriteRepository,
    RetrievedRow,
)
from sipromo.domain.entities.knowledge_document import KnowledgeChunk, KnowledgeDocument
from sipromo.domain.value_objects.content_type import DocumentStatus, DocumentType
from sipromo.infrastructure.db.models.new import KnowledgeChunk as ChunkModel
from sipromo.infrastructure.db.models.new import KnowledgeDocument as DocumentModel
from sipromo.infrastructure.db.session import get_current_session

TYPE_FILTERS = {
    DocumentType.BRAND_GUIDE: "brand_guide",
    DocumentType.PRODUCT_CATALOG: "product_catalog",
    DocumentType.FAQ: "faq",
    DocumentType.CAMPAIGN_EXAMPLE: "campaign_example",
    DocumentType.POLICY: "policy",
    DocumentType.OTHER: "other",
}


def _to_domain(row: DocumentModel) -> KnowledgeDocument:
    return KnowledgeDocument(
        document_id=str(row.id),
        umkm_id=str(row.umkm_id),
        title=row.title,
        document_type=DocumentType(row.document_type),
        status=DocumentStatus(row.status),
        checksum_sha256=row.checksum_sha256,
        cloudinary_public_id=row.cloudinary_public_id,
        created_at=row.created_at,
    )


class KnowledgeRepository(KnowledgeReadRepository, KnowledgeWriteRepository):
    async def get_document(self, umkm_id: UUID, document_id: UUID) -> KnowledgeDocument | None:
        session = get_current_session()
        result = await session.execute(
            select(DocumentModel).where(
                DocumentModel.umkm_id == umkm_id,
                DocumentModel.id == document_id,
            )
        )
        row = result.scalar_one_or_none()
        return _to_domain(row) if row is not None else None

    async def list_documents(
        self,
        umkm_id: UUID,
        status: DocumentStatus | None = None,
        document_type: DocumentType | None = None,
    ) -> list[KnowledgeDocument]:
        session = get_current_session()
        query = select(DocumentModel).where(DocumentModel.umkm_id == umkm_id)
        if status is not None:
            query = query.where(DocumentModel.status == status.value)
        if document_type is not None:
            query = query.where(DocumentModel.document_type == document_type.value)
        query = query.order_by(DocumentModel.created_at.desc())
        result = await session.execute(query)
        return [_to_domain(row) for row in result.scalars().all()]

    async def document_exists_by_checksum(self, umkm_id: UUID, checksum: str) -> bool:
        session = get_current_session()
        result = await session.execute(
            select(func.count(DocumentModel.id)).where(
                DocumentModel.umkm_id == umkm_id,
                DocumentModel.checksum_sha256 == checksum,
            )
        )
        return bool(result.scalar_one())

    async def vector_search(
        self,
        umkm_id: UUID,
        query_embedding: list[float],
        limit: int,
        document_types: list[DocumentType] | None = None,
    ) -> list[RetrievedRow]:
        session = get_current_session()
        distance = ChunkModel.embedding.cosine_distance(query_embedding)
        query = (
            select(
                ChunkModel.id,
                ChunkModel.document_id,
                DocumentModel.document_type,
                ChunkModel.content,
                ChunkModel.metadata_.label("metadata"),
                (1 - distance).label("similarity"),
            )
            .join(DocumentModel, DocumentModel.id == ChunkModel.document_id)
            .where(
                ChunkModel.umkm_id == umkm_id,
                DocumentModel.status == DocumentStatus.READY.value,
            )
        )
        if document_types:
            query = query.where(DocumentModel.document_type.in_([t.value for t in document_types]))
        query = query.order_by(distance).limit(limit)
        result = await session.execute(query)
        return [
            RetrievedRow(
                chunk_id=row.id,
                document_id=row.document_id,
                document_type=row.document_type,
                content=row.content,
                metadata=row.metadata or {},
                score=float(row.similarity),
            )
            for row in result.all()
        ]

    async def lexical_search(
        self,
        umkm_id: UUID,
        query: str,
        limit: int,
        document_types: list[DocumentType] | None = None,
    ) -> list[RetrievedRow]:
        session = get_current_session()
        ts_vector = func.to_tsvector("simple", ChunkModel.content)
        ts_query = func.plainto_tsquery("simple", query)
        query_stmt = (
            select(
                ChunkModel.id,
                ChunkModel.document_id,
                DocumentModel.document_type,
                ChunkModel.content,
                ChunkModel.metadata_.label("metadata"),
                func.ts_rank_cd(ts_vector, ts_query).label("rank"),
            )
            .join(DocumentModel, DocumentModel.id == ChunkModel.document_id)
            .where(
                ChunkModel.umkm_id == umkm_id,
                DocumentModel.status == DocumentStatus.READY.value,
                ts_vector.op("@@")(ts_query),
            )
        )
        if document_types:
            query_stmt = query_stmt.where(
                DocumentModel.document_type.in_([t.value for t in document_types])
            )
        query_stmt = query_stmt.order_by(func.ts_rank_cd(ts_vector, ts_query).desc()).limit(limit)
        result = await session.execute(query_stmt)
        return [
            RetrievedRow(
                chunk_id=row.id,
                document_id=row.document_id,
                document_type=row.document_type,
                content=row.content,
                metadata=row.metadata or {},
                score=float(row.rank),
            )
            for row in result.all()
        ]

    async def list_chunks_for_document(
        self, umkm_id: UUID, document_id: UUID
    ) -> list[KnowledgeChunk]:
        session = get_current_session()
        result = await session.execute(
            select(ChunkModel)
            .where(ChunkModel.umkm_id == umkm_id, ChunkModel.document_id == document_id)
            .order_by(ChunkModel.chunk_index)
        )
        chunks: list[KnowledgeChunk] = []
        for row in result.scalars().all():
            chunks.append(
                KnowledgeChunk(
                    chunk_id=str(row.id),
                    document_id=str(row.document_id),
                    umkm_id=str(row.umkm_id),
                    chunk_index=row.chunk_index,
                    content=row.content,
                    token_count=row.token_count,
                    embedding=list(row.embedding),
                    metadata=row.metadata_ or {},
                )
            )
        return chunks

    async def create_document(self, document: KnowledgeDocument) -> KnowledgeDocument:
        session = get_current_session()
        row = DocumentModel(
            id=UUID(str(document.document_id)),
            umkm_id=UUID(str(document.umkm_id)),
            title=document.title,
            document_type=document.document_type.value,
            source_type="upload",
            checksum_sha256=document.checksum_sha256 or "",
            status=document.status.value,
        )
        session.add(row)
        await session.flush()
        return document

    async def update_document_status(
        self, umkm_id: UUID, document_id: UUID, status: DocumentStatus
    ) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE knowledge_documents
                SET status = :status
                WHERE umkm_id = :umkm_id AND id = :document_id
            """),
            {"umkm_id": str(umkm_id), "document_id": str(document_id), "status": status.value},
        )

    async def update_document_metadata(
        self, umkm_id: UUID, document_id: UUID, **fields: object
    ) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE knowledge_documents
                SET metadata = metadata || :fields
                WHERE umkm_id = :umkm_id AND id = :document_id
            """),
            {
                "umkm_id": str(umkm_id),
                "document_id": str(document_id),
                "fields": json.dumps(
                    {k: v for k, v in fields.items() if v is not None},
                    ensure_ascii=False,
                    default=str,
                ),
            },
        )

    async def insert_chunks(self, chunks: list[KnowledgeChunk]) -> None:
        session = get_current_session()
        for chunk in chunks:
            session.add(
                ChunkModel(
                    id=UUID(str(chunk.chunk_id)),
                    document_id=UUID(str(chunk.document_id)),
                    umkm_id=UUID(str(chunk.umkm_id)),
                    chunk_index=chunk.chunk_index,
                    content=chunk.content,
                    token_count=chunk.token_count,
                    embedding=Vector(chunk.embedding),
                    metadata=chunk.metadata or {},
                )
            )
        await session.flush()

    async def list_chunks_by_document_type(
        self,
        umkm_id: UUID,
        document_types: list[DocumentType],
        limit: int,
    ) -> list[RetrievedRow]:
        """Chunks from always-relevant document types (brand guide, campaign
        examples) regardless of query score; used as global brand context."""
        session = get_current_session()
        rows = await session.execute(
            select(ChunkModel, DocumentModel.document_type)
            .join(DocumentModel, DocumentModel.id == ChunkModel.document_id)
            .where(
                ChunkModel.umkm_id == umkm_id,
                DocumentModel.status == DocumentStatus.READY.value,
                DocumentModel.document_type.in_([t.value for t in document_types]),
            )
            .order_by(ChunkModel.chunk_index)
            .limit(limit)
        )
        return [
            RetrievedRow(
                chunk_id=chunk.id,
                document_id=chunk.document_id,
                document_type=document_type,
                content=chunk.content,
                metadata=chunk.metadata_,
                score=0.0,
            )
            for chunk, document_type in rows.fetchall()
        ]

    async def delete_chunks_for_document(self, umkm_id: UUID, document_id: UUID) -> None:
        session = get_current_session()
        await session.execute(
            delete(ChunkModel).where(
                ChunkModel.umkm_id == umkm_id,
                ChunkModel.document_id == document_id,
            )
        )

    async def archive_document(self, umkm_id: UUID, document_id: UUID) -> None:
        await self.update_document_status(umkm_id, document_id, DocumentStatus.ARCHIVED)

    async def hard_delete_document(self, umkm_id: UUID, document_id: UUID) -> None:
        session = get_current_session()
        await session.execute(
            delete(DocumentModel).where(
                DocumentModel.umkm_id == umkm_id,
                DocumentModel.id == document_id,
            )
        )
