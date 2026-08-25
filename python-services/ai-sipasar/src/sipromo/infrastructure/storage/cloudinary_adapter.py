"""Cloudinary adapter. SDK calls are blocking; they are wrapped with
asyncio.to_thread so the FastAPI event loop never blocks (section 15.3)."""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass

import cloudinary
import cloudinary.api
import cloudinary.uploader

from sipromo.application.ports.object_storage import (
    ObjectStoragePort,
    StoredAsset,
    UploadAsset,
)
from sipromo.domain.exceptions import StorageError

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class CloudinaryConfig:
    cloud_name: str
    api_key: str
    api_secret: str
    secure: bool = True


class CloudinaryAdapter(ObjectStoragePort):
    def __init__(self, config: CloudinaryConfig) -> None:
        cloudinary.config(
            cloud_name=config.cloud_name,
            api_key=config.api_key,
            api_secret=config.api_secret,
            secure=config.secure,
        )
        self._config = config

    async def upload(self, asset: UploadAsset) -> StoredAsset:
        try:
            result = await asyncio.to_thread(self._do_upload, asset)
        except Exception as exc:
            logger.exception("cloudinary upload failed", extra={"folder": asset.folder})
            raise StorageError(f"upload failed: {type(exc).__name__}") from exc
        return StoredAsset(
            public_id=result.get("public_id", ""),
            secure_url=result.get("secure_url", ""),
            resource_type=result.get("resource_type", "raw"),
            format=result.get("format", ""),
            bytes=result.get("bytes", 0),
            width=result.get("width"),
            height=result.get("height"),
        )

    async def delete(self, public_id: str) -> None:
        if not public_id:
            return
        try:
            await asyncio.to_thread(cloudinary.uploader.destroy, public_id, resource_type="raw")
        except Exception:
            logger.exception("cloudinary delete failed", extra={"public_id": public_id})

    def _do_upload(self, asset: UploadAsset) -> dict:
        kwargs: dict = {
            "public_id": asset.public_id,
            "folder": asset.folder,
            "overwrite": False,
            "resource_type": asset.resource_type or "auto",
            "secure": True,
        }
        if asset.file_path is not None:
            return cloudinary.uploader.upload(asset.file_path, **kwargs)
        if asset.file_bytes is not None:
            return cloudinary.uploader.upload(asset.file_bytes, **{**kwargs, "use_filename": False})
        raise StorageError("upload asset has neither file_path nor file_bytes")
