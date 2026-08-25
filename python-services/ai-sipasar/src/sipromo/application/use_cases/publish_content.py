"""Publish content use case - creates a publish_jobs row only after approval."""

from __future__ import annotations

import uuid
from typing import Protocol

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    PublishContentCommand,
)
from sipromo.application.dto.promotion_responses import PublishJobDTO
from sipromo.application.ports.repositories import ContentReadRepository
from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.domain.exceptions import (
    ApprovalRequiredError,
    ContentNotFoundError,
    PublishPlatformError,
    UnauthorizedActionError,
)

ALLOWED_PUBLISH_PLATFORMS = {"instagram", "facebook", "generic"}


class PublishJobRepositoryPort(Protocol):
    async def create_job(
        self,
        *,
        umkm_id: object,
        content_id: object,
        platform: str,
        scheduled_at: object | None,
        created_by: object,
        job_id: uuid.UUID,
    ) -> None: ...

    async def find_job(
        self, umkm_id: object, content_id: object, platform: str
    ) -> object | None: ...


class PublishContentUseCase:
    def __init__(
        self,
        *,
        unit_of_work: UnitOfWorkPort,
        content_read: ContentReadRepository,
        publish_job_repo: PublishJobRepositoryPort,
    ) -> None:
        self._unit_of_work = unit_of_work
        self._content_read = content_read
        self._publish_job_repo = publish_job_repo

    async def execute(
        self, command: PublishContentCommand, actor: AuthenticatedActor
    ) -> PublishJobDTO:
        if actor.role not in {"owner", "staff"}:
            raise UnauthorizedActionError("Only owner or staff can publish content")
        if command.platform not in ALLOWED_PUBLISH_PLATFORMS:
            raise PublishPlatformError(f"Platform '{command.platform}' tidak didukung")

        content = await self._content_read.get_content(actor.umkm_id, command.content_id)
        if content is None:
            raise ContentNotFoundError()

        approved = await self._content_read.get_approved_revision(actor.umkm_id, command.content_id)
        if approved is None:
            raise ApprovalRequiredError()

        job_id = uuid.uuid4()
        async with self._unit_of_work.begin():
            await self._publish_job_repo.create_job(
                umkm_id=actor.umkm_id,
                content_id=command.content_id,
                platform=command.platform,
                scheduled_at=command.scheduled_at,
                created_by=actor.user_id,
                job_id=job_id,
            )

        return PublishJobDTO(
            job_id=job_id,
            content_id=command.content_id,
            platform=command.platform,
            status="queued",
            scheduled_at=command.scheduled_at,
        )
