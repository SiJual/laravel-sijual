"""Knowledge documents API (section 16.1)."""

from __future__ import annotations

import logging
from uuid import UUID

from fastapi import APIRouter, Depends, File, Form, Request, UploadFile
from fastapi.responses import JSONResponse

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    IngestKnowledgeCommand,
)
from sipromo.application.dto.promotion_responses import KnowledgeDocumentDTO
from sipromo.bootstrap.container import Container
from sipromo.domain.exceptions import DomainError, UnsupportedFileTypeError
from sipromo.domain.value_objects.content_type import DocumentStatus, DocumentType
from sipromo.infrastructure.observability.telemetry import obfuscate
from sipromo.presentation.api.dependencies import get_actor

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/knowledge", tags=["knowledge"])

TYPE_ALIASES = {
    "brand_guide": DocumentType.BRAND_GUIDE,
    "product_catalog": DocumentType.PRODUCT_CATALOG,
    "faq": DocumentType.FAQ,
    "campaign_example": DocumentType.CAMPAIGN_EXAMPLE,
    "policy": DocumentType.POLICY,
    "other": DocumentType.OTHER,
}


def _parse_document_type(raw: str) -> DocumentType:
    document_type = TYPE_ALIASES.get(raw)
    if document_type is None:
        raise UnsupportedFileTypeError(f"unknown document_type '{raw}'")
    return document_type


@router.post(
    "/documents",
    status_code=202,
    response_model=KnowledgeDocumentDTO,
    summary="Upload knowledge document",
    description=(
        "Multipart upload → MIME/size validation → SHA-256 dedup → Cloudinary (if configured) "
        "→ chunk (500-800 tokens) → embed (`EMBEDDING_DIM`) → `knowledge_chunks`. "
        "Duplicate `(umkm_id, checksum)` is idempotent. Returns `202` with document status `pending/ready`."
    ),
)
async def upload_document(
    request: Request,
    file: UploadFile = File(...),
    title: str = Form(...),
    document_type: str = Form("other"),
    actor: AuthenticatedActor = Depends(get_actor),
) -> KnowledgeDocumentDTO:
    container: Container = request.app.state.container
    content = await file.read()
    command = IngestKnowledgeCommand(
        title=title[:255],
        document_type=_parse_document_type(document_type),
        filename=file.filename or "document",
        content_bytes=content,
        mime_type=file.content_type or "application/octet-stream",
    )
    logger.info(
        "knowledge_upload_received",
        extra={"umkm": obfuscate(actor.umkm_id), "size": len(content)},
    )
    return await container.ingest_knowledge.ingest_file(command, actor)


@router.get(
    "/documents",
    response_model=list[KnowledgeDocumentDTO],
    summary="List knowledge documents",
    description="Tenant-scoped list with optional `?status=&document_type=` filters. Enriched with `chunk_count`.",
)
async def list_documents(
    request: Request,
    status: str | None = None,
    document_type: str | None = None,
    actor: AuthenticatedActor = Depends(get_actor),
) -> list[KnowledgeDocumentDTO]:
    container: Container = request.app.state.container
    status_enum = DocumentStatus(status) if status else None
    type_enum = _parse_document_type(document_type) if document_type else None
    documents = await container.knowledge_repo.list_documents(
        actor.umkm_id, status=status_enum, document_type=type_enum
    )
    result: list[KnowledgeDocumentDTO] = []
    for document in documents:
        chunks = await container.knowledge_repo.list_chunks_for_document(
            actor.umkm_id, UUID(document.document_id)
        )
        result.append(
            KnowledgeDocumentDTO(
                document_id=UUID(document.document_id),
                umkm_id=actor.umkm_id,
                title=document.title,
                document_type=document.document_type.value,
                source_type="upload",
                status=document.status.value,
                checksum_sha256=document.checksum_sha256,
                chunk_count=len(chunks),
            )
        )
    return result


@router.get(
    "/documents/{document_id}",
    response_model=KnowledgeDocumentDTO,
    summary="Get knowledge document",
    description="Fetch a single document by id (tenant-scoped). `404 DOCUMENT_NOT_FOUND` if foreign.",
)
async def get_document(
    document_id: UUID,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> KnowledgeDocumentDTO:
    container: Container = request.app.state.container
    document = await container.knowledge_repo.get_document(actor.umkm_id, document_id)
    if document is None:
        raise DomainError("Dokumen tidak ditemukan", error_code="DOCUMENT_NOT_FOUND")
    chunks = await container.knowledge_repo.list_chunks_for_document(actor.umkm_id, document_id)
    return KnowledgeDocumentDTO(
        document_id=document_id,
        umkm_id=actor.umkm_id,
        title=document.title,
        document_type=document.document_type.value,
        source_type="upload",
        status=document.status.value,
        checksum_sha256=document.checksum_sha256,
        chunk_count=len(chunks),
    )


@router.delete(
    "/documents/{document_id}",
    status_code=204,
    summary="Archive or hard-delete a document",
    description="`?hard=false` (default) archives (`status=archived`); `?hard=true` hard-deletes chunks + Cloudinary asset.",
)
async def delete_document(
    document_id: UUID,
    request: Request,
    hard: bool = False,
    actor: AuthenticatedActor = Depends(get_actor),
) -> JSONResponse:
    container: Container = request.app.state.container
    if hard:
        await container.ingest_knowledge.hard_delete(document_id, actor)
    else:
        await container.ingest_knowledge.archive(document_id, actor)
    return JSONResponse(status_code=204, content=None)
