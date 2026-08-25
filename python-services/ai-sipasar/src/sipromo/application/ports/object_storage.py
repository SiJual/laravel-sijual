"""Object storage port (Cloudinary adapter implements it)."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Protocol


@dataclass
class UploadAsset:
    folder: str
    public_id: str
    file_path: str | None = None
    file_bytes: bytes | None = None
    resource_type: str = "auto"
    mime_type: str | None = None


@dataclass
class StoredAsset:
    public_id: str
    secure_url: str
    resource_type: str
    format: str
    bytes: int
    width: int | None = field(default=None)
    height: int | None = field(default=None)


class ObjectStoragePort(Protocol):
    async def upload(self, asset: UploadAsset) -> StoredAsset: ...

    async def delete(self, public_id: str) -> None: ...
