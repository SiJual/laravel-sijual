from __future__ import annotations

from datetime import UTC, datetime

from sipromo.domain.value_objects.content_type import DocumentStatus, DocumentType


class KnowledgeDocument:
    """Knowledge document entity with explicit status lifecycle."""

    ALLOWED_TRANSITIONS: dict[str, set[str]] = {
        DocumentStatus.PENDING.value: {
            DocumentStatus.PROCESSING.value,
            DocumentStatus.FAILED.value,
            DocumentStatus.ARCHIVED.value,
        },
        DocumentStatus.PROCESSING.value: {
            DocumentStatus.READY.value,
            DocumentStatus.FAILED.value,
            DocumentStatus.ARCHIVED.value,
        },
        DocumentStatus.READY.value: {DocumentStatus.ARCHIVED.value},
        DocumentStatus.FAILED.value: {
            DocumentStatus.PENDING.value,
            DocumentStatus.PROCESSING.value,
            DocumentStatus.ARCHIVED.value,
        },
        DocumentStatus.ARCHIVED.value: set(),
    }

    def __init__(
        self,
        *,
        document_id: str,
        umkm_id: str,
        title: str,
        document_type: DocumentType,
        status: DocumentStatus = DocumentStatus.PENDING,
        checksum_sha256: str | None = None,
        cloudinary_public_id: str | None = None,
        created_at: datetime | None = None,
    ) -> None:
        self.document_id = document_id
        self.umkm_id = umkm_id
        self.title = title
        self.document_type = document_type
        self.status = status
        self.checksum_sha256 = checksum_sha256
        self.cloudinary_public_id = cloudinary_public_id
        self.created_at = created_at or datetime.now(UTC)

    def transition_to(self, new_status: DocumentStatus) -> None:
        allowed = self.ALLOWED_TRANSITIONS.get(self.status.value, set())
        if new_status.value not in allowed:
            raise ValueError(f"Invalid status transition {self.status.value} -> {new_status.value}")
        self.status = new_status

    def archive(self) -> None:
        self.transition_to(DocumentStatus.ARCHIVED)


class KnowledgeChunk:
    def __init__(
        self,
        *,
        chunk_id: str,
        document_id: str,
        umkm_id: str,
        chunk_index: int,
        content: str,
        token_count: int,
        embedding: list[float],
        metadata: dict | None = None,
    ) -> None:
        self.chunk_id = chunk_id
        self.document_id = document_id
        self.umkm_id = umkm_id
        self.chunk_index = chunk_index
        self.content = content
        self.token_count = token_count
        self.embedding = embedding
        self.metadata = metadata or {}
