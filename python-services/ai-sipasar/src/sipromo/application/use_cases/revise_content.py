"""Revise content use case - creates a new revision, never overwrites history."""

from __future__ import annotations

from datetime import UTC, datetime

from sipromo.application.dto.promotion_requests import (
    AuthenticatedActor,
    ReviseContentCommand,
)
from sipromo.application.dto.promotion_responses import RevisionDTO
from sipromo.application.ports.repositories import (
    ContentReadRepository,
    ContentWriteRepository,
)
from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.domain.exceptions import (
    ContentNotFoundError,
    RevisionNotFoundError,
    UnauthorizedActionError,
)

IMMUTABLE_PAYLOAD_KEYS = {"_brief", "_prompt_version", "_revision_reason"}


class ReviseContentUseCase:
    def __init__(
        self,
        *,
        unit_of_work: UnitOfWorkPort,
        content_read: ContentReadRepository,
        content_write: ContentWriteRepository,
    ) -> None:
        self._unit_of_work = unit_of_work
        self._content_read = content_read
        self._content_write = content_write

    async def execute(
        self, command: ReviseContentCommand, actor: AuthenticatedActor
    ) -> RevisionDTO:
        if actor.role not in {"owner", "staff"}:
            raise UnauthorizedActionError("Only owner or staff can revise content")

        content = await self._content_read.get_content(actor.umkm_id, command.content_id)
        if content is None:
            raise ContentNotFoundError()

        latest = await self._content_read.get_latest_revision_payload(
            actor.umkm_id, command.content_id
        )
        if latest is None:
            raise RevisionNotFoundError()

        payload = dict(latest)
        if command.edited_payload:
            for key, value in command.edited_payload.items():
                if key in payload and key not in IMMUTABLE_PAYLOAD_KEYS:
                    payload[key] = value
        if command.feedback:
            payload["_feedback"] = command.feedback
        payload["_revision_reason"] = command.change_reason or "user revision"

        new_version = int(content.version or 1) + 1
        now = datetime.now(UTC)

        async with self._unit_of_work.begin():
            await self._content_write.create_revision(
                umkm_id=actor.umkm_id,
                content_id=command.content_id,
                version=new_version,
                payload=payload,
                changed_by=actor.user_id,
                change_reason=command.change_reason or "user revision",
            )
            await self._content_write.update_content_status(
                actor.umkm_id, command.content_id, "draft"
            )

        return RevisionDTO(
            content_id=command.content_id,
            version=new_version,
            payload=payload,
            change_reason=command.change_reason,
            created_at=now,
        )
