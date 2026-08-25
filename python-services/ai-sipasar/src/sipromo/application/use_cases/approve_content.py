"""Approve content use case - explicit human approval before any publishing."""

from __future__ import annotations

from datetime import UTC, datetime

from sipromo.application.dto.promotion_requests import (
    ApproveContentCommand,
    AuthenticatedActor,
)
from sipromo.application.dto.promotion_responses import ApprovalDTO
from sipromo.application.ports.repositories import (
    ContentReadRepository,
    ContentWriteRepository,
)
from sipromo.application.ports.transaction_manager import UnitOfWorkPort
from sipromo.domain.exceptions import (
    ApprovalAlreadyDecidedError,
    ContentNotFoundError,
    UnauthorizedActionError,
)
from sipromo.domain.value_objects.content_type import ApprovalDecision


class ApproveContentUseCase:
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
        self, command: ApproveContentCommand, actor: AuthenticatedActor
    ) -> ApprovalDTO:
        if actor.role not in {"owner", "staff"}:
            raise UnauthorizedActionError("Only owner or staff can approve content")

        content = await self._content_read.get_content(actor.umkm_id, command.content_id)
        if content is None:
            raise ContentNotFoundError()

        if await self._content_read.has_approval(
            actor.umkm_id, command.content_id, ApprovalDecision.APPROVED
        ):
            raise ApprovalAlreadyDecidedError()

        now = datetime.now(UTC)
        async with self._unit_of_work.begin():
            await self._content_write.create_approval(
                umkm_id=actor.umkm_id,
                content_id=command.content_id,
                decided_by=actor.user_id,
                decision=command.decision,
                notes=command.notes,
            )

        return ApprovalDTO(
            content_id=command.content_id,
            decision=command.decision.value,
            notes=command.notes,
            decided_by=actor.user_id,
            created_at=now,
        )
