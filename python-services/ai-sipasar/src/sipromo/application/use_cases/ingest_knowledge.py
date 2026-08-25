"""Knowledge ingestion use case.

Pipeline per section 12.2: MIME/size validation -> SHA-256 -> Cloudinary upload
-> create document (pending) -> extract -> chunk -> embed -> bulk insert ->
mark ready. Failure marks the document failed and removes partial chunks
(compensating action).
"""

from __future__ import annotations

import hashlib
import logging
import uuid
from typing import Protocol

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    IngestKnowledgeCommand,
    IngestTextKnowledgeCommand,
)
from sipromo.application.dto.promotion_responses import KnowledgeDocumentDTO
from sipromo.application.ports.embeddings import EmbeddingPort
from sipromo.application.ports.object_storage import ObjectStoragePort, UploadAsset
from sipromo.application.ports.repositories import (
    KnowledgeReadRepository,
    KnowledgeWriteRepository,
)
from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.domain.entities.knowledge_document import KnowledgeChunk, KnowledgeDocument
from sipromo.domain.exceptions import (
    DomainError,
    FileTooLargeError,
    UnsupportedFileTypeError,
)
from sipromo.domain.value_objects.content_type import DocumentStatus

logger = logging.getLogger(__name__)

SUPPORTED_MIME_TYPES = {
    "text/plain",
    "text/markdown",
    "text/csv",
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/msword",
}


class TextExtractorPort(Protocol):
    async def extract(self, content: bytes, mime_type: str) -> str: ...


class ChunkerPort(Protocol):
    def chunk(
        self, text: str, *, document_id: str, umkm_id: str, title: str
    ) -> list[KnowledgeChunk]: ...


class IngestKnowledgeUseCase:
    def __init__(
        self,
        *,
        unit_of_work: UnitOfWorkPort,
        storage: ObjectStoragePort,
        extractor: TextExtractorPort,
        chunker: ChunkerPort,
        embeddings: EmbeddingPort,
        knowledge_read: KnowledgeReadRepository,
        knowledge_write: KnowledgeWriteRepository,
        upload_max_bytes: int,
        folder_prefix: str = "sipromo",
    ) -> None:
        self._unit_of_work = unit_of_work
        self._storage = storage
        self._extractor = extractor
        self._chunker = chunker
        self._embeddings = embeddings
        self._knowledge_read = knowledge_read
        self._knowledge_write = knowledge_write
        self._upload_max_bytes = upload_max_bytes
        self._folder_prefix = folder_prefix

    async def ingest_file(
        self, command: IngestKnowledgeCommand, actor: AuthenticatedActor
    ) -> KnowledgeDocumentDTO:
        if len(command.content_bytes) > self._upload_max_bytes:
            raise FileTooLargeError(self._upload_max_bytes)
        if command.mime_type not in SUPPORTED_MIME_TYPES:
            raise UnsupportedFileTypeError(command.mime_type)

        checksum = hashlib.sha256(command.content_bytes).hexdigest()
        if await self._knowledge_read.document_exists_by_checksum(actor.umkm_id, checksum):
            raise DomainError(
                "Dokumen dengan konten identik sudah diunggah",
                error_code="DUPLICATE_DOCUMENT",
            )

        document_id = uuid.uuid4()
        document = KnowledgeDocument(
            document_id=str(document_id),
            umkm_id=str(actor.umkm_id),
            title=command.title,
            document_type=command.document_type,
            checksum_sha256=checksum,
        )
        async with self._unit_of_work.begin():
            await self._knowledge_write.create_document(document)

        folder = f"{self._folder_prefix}/{actor.umkm_id}/knowledge/{document_id}"
        try:
            stored = await self._storage.upload(
                UploadAsset(
                    folder=folder,
                    public_id=str(document_id),
                    file_bytes=command.content_bytes,
                    resource_type="raw",
                    mime_type=command.mime_type,
                )
            )
            await self._set_status(actor.umkm_id, document_id, DocumentStatus.PROCESSING)
            await self._set_document_metadata(
                actor.umkm_id,
                document_id,
                cloudinary_public_id=stored.public_id,
                source_url=stored.secure_url,
                mime_type=command.mime_type,
                storage_bytes=stored.bytes,
            )
            text = await self._extractor.extract(command.content_bytes, command.mime_type)
            chunks = self._chunker.chunk(
                text,
                document_id=str(document_id),
                umkm_id=str(actor.umkm_id),
                title=command.title,
            )
            embeddings = await self._embeddings.embed_documents([c.content for c in chunks])
            for chunk, embedding in zip(chunks, embeddings, strict=True):
                chunk.embedding = embedding

            async with self._unit_of_work.begin():
                await self._knowledge_write.insert_chunks(chunks)
                await self._knowledge_write.update_document_status(
                    actor.umkm_id, document_id, DocumentStatus.READY
                )

            logger.info(
                "knowledge_document_ready",
                extra={
                    "document_id": str(document_id),
                    "umkm_id": str(actor.umkm_id),
                    "chunks": len(chunks),
                },
            )
            return KnowledgeDocumentDTO(
                document_id=document_id,
                umkm_id=actor.umkm_id,
                title=command.title,
                document_type=command.document_type.value,
                source_type=command.source_type,
                status=DocumentStatus.READY.value,
                checksum_sha256=checksum,
                mime_type=command.mime_type,
                chunk_count=len(chunks),
            )
        except Exception as exc:
            await self._mark_failed(actor.umkm_id, document_id)
            await self._cleanup_partial(actor.umkm_id, document_id)
            if isinstance(exc, DomainError):
                raise
            logger.exception("knowledge ingestion failed", extra={"document_id": str(document_id)})
            raise

    async def ingest_text(
        self, command: IngestTextKnowledgeCommand, actor: AuthenticatedActor
    ) -> KnowledgeDocumentDTO:
        """Manual text ingestion (no Cloudinary round-trip) for seeds/tests."""
        checksum = hashlib.sha256(command.text.encode("utf-8")).hexdigest()
        if await self._knowledge_read.document_exists_by_checksum(actor.umkm_id, checksum):
            raise DomainError(
                "Dokumen dengan konten identik sudah diunggah",
                error_code="DUPLICATE_DOCUMENT",
            )
        document_id = uuid.uuid4()
        document = KnowledgeDocument(
            document_id=str(document_id),
            umkm_id=str(actor.umkm_id),
            title=command.title,
            document_type=command.document_type,
            checksum_sha256=checksum,
        )
        async with self._unit_of_work.begin():
            await self._knowledge_write.create_document(document)

        chunks = self._chunker.chunk(
            command.text,
            document_id=str(document_id),
            umkm_id=str(actor.umkm_id),
            title=command.title,
        )
        embeddings = await self._embeddings.embed_documents([c.content for c in chunks])
        for chunk, embedding in zip(chunks, embeddings, strict=True):
            chunk.embedding = embedding

        async with self._unit_of_work.begin():
            await self._knowledge_write.insert_chunks(chunks)
            await self._knowledge_write.update_document_status(
                actor.umkm_id, document_id, DocumentStatus.READY
            )

        return KnowledgeDocumentDTO(
            document_id=document_id,
            umkm_id=actor.umkm_id,
            title=command.title,
            document_type=command.document_type.value,
            source_type=command.source_type,
            status=DocumentStatus.READY.value,
            checksum_sha256=checksum,
            chunk_count=len(chunks),
        )

    async def archive(self, document_id: uuid.UUID, actor: AuthenticatedActor) -> None:
        await self._knowledge_write.archive_document(actor.umkm_id, document_id)

    async def hard_delete(self, document_id: uuid.UUID, actor: AuthenticatedActor) -> None:
        document = await self._knowledge_read.get_document(actor.umkm_id, document_id)
        if document is None:
            raise DomainError("Dokumen tidak ditemukan", error_code="DOCUMENT_NOT_FOUND")
        async with self._unit_of_work.begin():
            await self._knowledge_write.hard_delete_document(actor.umkm_id, document_id)
        if document.cloudinary_public_id:
            await self._storage.delete(document.cloudinary_public_id)

    # ------------------------------------------------------------------ #

    async def _set_status(
        self, umkm_id: uuid.UUID, document_id: uuid.UUID, status: DocumentStatus
    ) -> None:
        async with self._unit_of_work.begin():
            await self._knowledge_write.update_document_status(umkm_id, document_id, status)

    async def _set_document_metadata(
        self, umkm_id: uuid.UUID, document_id: uuid.UUID, **fields: object
    ) -> None:
        async with self._unit_of_work.begin():
            await self._knowledge_write.update_document_metadata(umkm_id, document_id, **fields)

    async def _mark_failed(self, umkm_id: uuid.UUID, document_id: uuid.UUID) -> None:
        try:
            await self._set_status(umkm_id, document_id, DocumentStatus.FAILED)
        except Exception:
            logger.exception(
                "failed to mark document failed", extra={"document_id": str(document_id)}
            )

    async def _cleanup_partial(self, umkm_id: uuid.UUID, document_id: uuid.UUID) -> None:
        try:
            async with self._unit_of_work.begin():
                await self._knowledge_write.delete_chunks_for_document(umkm_id, document_id)
        except Exception:
            logger.exception(
                "failed to clean partial chunks", extra={"document_id": str(document_id)}
            )
