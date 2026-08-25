"""Assets API: uploads (logo, product photos) to Cloudinary (section 15)."""

from __future__ import annotations

import logging
from uuid import uuid4

from fastapi import APIRouter, Depends, File, Request, UploadFile
from fastapi.responses import JSONResponse

from sipromo.application.dto.promotion_requests import AuthenticatedActor
from sipromo.application.ports.object_storage import UploadAsset
from sipromo.bootstrap.container import Container
from sipromo.domain.exceptions import ConfigurationError, FileTooLargeError
from sipromo.infrastructure.observability.telemetry import obfuscate
from sipromo.presentation.api.dependencies import get_actor

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/assets", tags=["assets"])

MAX_ASSET_BYTES = 5 * 1024 * 1024


@router.post(
    "/upload",
    summary="Upload media asset",
    description=(
        "Server-side Cloudinary upload for `kind=logo|product` (image, ≤5 MiB). "
        "Stored as `sipromo/{umkm_id}/{kind}/{uuid}` with generated UUID public_id. "
        "Returns `public_id`, `secure_url`, `format`, `bytes`, `width`, `height`. "
        "Fails `503` if Cloudinary not configured."
    ),
)
async def upload_asset(
    request: Request,
    kind: str = "product",
    file: UploadFile = File(...),
    actor: AuthenticatedActor = Depends(get_actor),
) -> JSONResponse:
    """Upload logo/product photo. Returns Cloudinary public metadata only.

    Usage: /api/v1/assets/upload?kind=logo|product
    """
    if kind not in {"logo", "product"}:
        return JSONResponse(
            {"error": {"code": "BAD_REQUEST", "message": "kind must be 'logo' or 'product'"}},
            status_code=400,
        )
    container: Container = request.app.state.container
    if container.storage is None:
        raise ConfigurationError("Cloudinary tidak dikonfigurasi")
    content = await file.read()
    if len(content) > MAX_ASSET_BYTES:
        raise FileTooLargeError(MAX_ASSET_BYTES)
    asset_id = uuid4()
    stored = await container.storage.upload(
        UploadAsset(
            folder=f"sipromo/{actor.umkm_id}/{kind}",
            public_id=str(asset_id),
            file_bytes=content,
            resource_type="image",
            mime_type=file.content_type,
        )
    )
    logger.info("asset_uploaded", extra={"umkm": obfuscate(actor.umkm_id), "kind": kind})
    return JSONResponse(
        {
            "asset_id": asset_id,
            "public_id": stored.public_id,
            "secure_url": stored.secure_url,
            "format": stored.format,
            "bytes": stored.bytes,
            "width": stored.width,
            "height": stored.height,
        }
    )
