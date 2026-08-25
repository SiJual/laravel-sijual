"""Promotions API: generate, read, revise, approval, publish (section 16.2)."""

from __future__ import annotations

from datetime import datetime
from uuid import UUID

from fastapi import APIRouter, Depends, Header, HTTPException, Request
from pydantic import BaseModel, Field

from sipromo.application.dto.promotion_requests import (
    ApproveContentCommand,
    AuthenticatedActor,
    GeneratePromotionCommand,
    PublishContentCommand,
    ReviseContentCommand,
)
from sipromo.application.dto.promotion_responses import (
    ApprovalDTO,
    PromotionDraftDTO,
    PublishJobDTO,
    RevisionDTO,
)
from sipromo.bootstrap.container import Container
from sipromo.domain.value_objects.content_type import ApprovalDecision
from sipromo.domain.value_objects.promotion_brief import PromotionBrief
from sipromo.presentation.api.dependencies import (
    IdempotencyGuard,
    error_payload,
    get_actor,
)

router = APIRouter(prefix="/promotions", tags=["promotions"])


class ReviseRequest(BaseModel):
    feedback: str | None = Field(default=None, max_length=4000)
    edited_payload: dict | None = None
    change_reason: str | None = Field(default=None, max_length=1000)


class ApproveRequest(BaseModel):
    decision: ApprovalDecision
    notes: str | None = Field(default=None, max_length=2000)


class PublishRequest(BaseModel):
    platform: str
    scheduled_at: datetime | None = None


@router.post(
    "/generate",
    status_code=201,
    response_model=PromotionDraftDTO,
    summary="Generate promotion draft",
    description=(
        "Generate a grounded promotion draft via hybrid RAG + 7 read tools + OpenAI tool loop. "
        "Validates product ownership, retrieves tenant-scoped knowledge (RRF), runs the bounded agent loop, "
        "validates claims deterministically and persists `draft + sources + trace + revision`. "
        "Always returns `requires_human_review=true`. Idempotent via `Idempotency-Key` header."
    ),
    response_description="Created draft with provenance, warnings and evidence",
)
async def generate_promotion(
    request: Request,
    brief: PromotionBrief,
    actor: AuthenticatedActor = Depends(get_actor),
    idempotency_key: str | None = Header(default=None, alias="Idempotency-Key"),
) -> PromotionDraftDTO:
    container: Container = request.app.state.container
    guard: IdempotencyGuard | None = None
    if idempotency_key:
        guard = IdempotencyGuard(container, actor, idempotency_key, brief.model_dump(mode="json"))
        replay = await guard.begin()
        if replay:
            raise HTTPException(
                status_code=409,
                detail=error_payload(
                    "IDEMPOTENCY_REPLAY", f"request already processed (hash {replay})"
                ),
            )
    draft = await container.generate_promotion.execute(GeneratePromotionCommand(brief=brief), actor)
    if guard is not None:
        await guard.complete(draft.model_dump(mode="json"))
    return draft


@router.get(
    "/{content_id}",
    response_model=PromotionDraftDTO,
    summary="Get promotion draft",
    description="Retrieve the latest draft and revision payload for a tenant-scoped `content_id`. 404 if foreign tenant.",
)
async def get_promotion(
    content_id: UUID,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> PromotionDraftDTO:
    container: Container = request.app.state.container
    content = await container.content_repo.get_content(actor.umkm_id, content_id)
    if content is None:
        raise HTTPException(
            status_code=404,
            detail=error_payload("CONTENT_NOT_FOUND", "content not found"),
        )
    revision = await container.content_repo.get_latest_revision_payload(actor.umkm_id, content_id)
    revision = revision or {}
    return PromotionDraftDTO(
        content_id=content_id,
        generation_run_id=UUID(int=0),
        status=content.status,
        version=content.version,
        title=content.title,
        primary_copy=content.primary_copy,
        caption=content.caption,
        hashtags=content.hashtags,
        call_to_action=content.call_to_action,
        visual_brief=content.visual_brief,
        target_audience_summary=revision.get("target_audience_summary", ""),
        rationale=revision.get("rationale", []),
        claims=revision.get("claims", []),
        evidence=[],
        warnings=revision.get("warnings", []),
        requires_human_review=True,
    )


@router.post(
    "/{content_id}/revisions",
    status_code=201,
    response_model=RevisionDTO,
    summary="Create a revision",
    description="Append a new immutable revision (feedback / edited payload). History is never overwritten.",
)
async def revise_promotion(
    content_id: UUID,
    body: ReviseRequest,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> RevisionDTO:
    container: Container = request.app.state.container
    return await container.revise_content.execute(
        ReviseContentCommand(
            content_id=content_id,
            feedback=body.feedback or "",
            edited_payload=body.edited_payload,
            change_reason=body.change_reason,
        ),
        actor,
    )


@router.post(
    "/{content_id}/approval",
    response_model=ApprovalDTO,
    summary="Decide on a promotion",
    description="Record `approved` / `rejected` / `changes_requested` for the latest revision. Only `owner`/`staff` may approve.",
)
async def approve_promotion(
    content_id: UUID,
    body: ApproveRequest,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> ApprovalDTO:
    container: Container = request.app.state.container
    return await container.approve_content.execute(
        ApproveContentCommand(
            content_id=content_id,
            decision=body.decision,
            notes=body.notes,
        ),
        actor,
    )


@router.post(
    "/{content_id}/publish",
    status_code=201,
    response_model=PublishJobDTO,
    summary="Publish an approved promotion",
    description="Create a `sipromo_jobs` publish job. Requires an `approved` revision — otherwise `409 APPROVAL_REQUIRED`. Uses `FOR UPDATE SKIP LOCKED`.",
)
async def publish_promotion(
    content_id: UUID,
    body: PublishRequest,
    request: Request,
    actor: AuthenticatedActor = Depends(get_actor),
) -> PublishJobDTO:
    container: Container = request.app.state.container
    return await container.publish_content.execute(
        PublishContentCommand(
            content_id=content_id,
            platform=body.platform,
            scheduled_at=body.scheduled_at,
        ),
        actor,
    )
