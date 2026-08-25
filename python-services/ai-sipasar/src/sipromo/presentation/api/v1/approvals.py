"""Approvals listing API (approval decisions are recorded via
POST /promotions/{id}/approval in promotions.py)."""

from __future__ import annotations

from uuid import UUID

from fastapi import APIRouter, Depends, Request
from sqlalchemy import select

from sipromo.application.dto.promotion_requests import AuthenticatedActor
from sipromo.bootstrap.container import Container
from sipromo.domain.exceptions import DomainError
from sipromo.infrastructure.db.models.legacy import ContentAsset
from sipromo.infrastructure.db.models.new import ContentApproval
from sipromo.infrastructure.db.session import get_current_session
from sipromo.presentation.api.dependencies import get_actor

router = APIRouter(prefix="/promotions", tags=["approvals"])


@router.get(
    "/{content_id}/approvals",
    summary="List approvals for a promotion",
    description="Tenant-scoped approval history (`approved`/`rejected`/`changes_requested`) ordered by `created_at desc`.",
)
async def list_approvals(
    content_id: UUID,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> dict:
    container: Container = request.app.state.container
    async with container.unit_of_work.begin():
        content = await container.content_repo.get_content(actor.umkm_id, content_id)
        if content is None:
            raise DomainError("Content tidak ditemukan", error_code="CONTENT_NOT_FOUND")
        session = get_current_session()
        result = await session.execute(
            select(ContentApproval)
            .join(ContentAsset, ContentAsset.id == ContentApproval.content_asset_id)
            .where(
                ContentAsset.umkm_id == actor.umkm_id,
                ContentApproval.content_asset_id == content_id,
            )
            .order_by(ContentApproval.created_at.desc())
        )
        return {
            "content_id": str(content_id),
            "approvals": [
                {
                    "id": str(row.id),
                    "decision": row.decision,
                    "notes": row.notes,
                    "decided_by": str(row.decided_by),
                    "created_at": row.created_at.isoformat(),
                }
                for row in result.scalars().all()
            ],
        }
