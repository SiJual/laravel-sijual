"""Membership, idempotency, and publish-job repositories."""

from __future__ import annotations

from datetime import UTC, datetime, timedelta
from uuid import UUID

from sqlalchemy import select, text
from sqlalchemy.ext.asyncio import AsyncSession

from sipromo.application.ports.repositories import (
    IdempotencyRepository,
    MembershipRepository,
)
from sipromo.infrastructure.db.models.legacy import PublishJob
from sipromo.infrastructure.db.models.new import IdempotencyKey, UmkmMembership
from sipromo.infrastructure.db.session import get_current_session


class MembershipRepositoryImpl(MembershipRepository):
    async def get_membership(self, umkm_id: UUID, user_id: UUID) -> str | None:
        session = get_current_session()
        result = await session.execute(
            select(UmkmMembership.role).where(
                UmkmMembership.umkm_id == umkm_id,
                UmkmMembership.user_id == user_id,
                UmkmMembership.status == "active",
            )
        )
        return result.scalar_one_or_none()

    async def get_membership_for_user(
        self, user_id: UUID, session: AsyncSession | None = None
    ) -> tuple[UUID, str] | None:
        session = session or get_current_session()
        result = await session.execute(
            select(UmkmMembership.umkm_id, UmkmMembership.role)
            .where(
                UmkmMembership.user_id == user_id,
                UmkmMembership.status == "active",
            )
            .order_by(UmkmMembership.created_at)
            .limit(1)
        )
        row = result.first()
        return (row[0], row[1]) if row is not None else None


class IdempotencyRepositoryImpl(IdempotencyRepository):
    async def get_or_create(
        self,
        *,
        scope: str,
        request_hash: str,
        ttl_seconds: int,
    ) -> tuple[str, str | None]:
        session = get_current_session()
        now = datetime.now(UTC)
        result = await session.execute(
            select(IdempotencyKey.status, IdempotencyKey.response_hash).where(
                IdempotencyKey.scope == scope,
                IdempotencyKey.expires_at > now,
            )
        )
        row = result.first()
        if row is not None:
            return row.status, row.response_hash
        entry = IdempotencyKey(
            scope=scope,
            request_hash=request_hash,
            status="in_flight",
            expires_at=now + timedelta(seconds=ttl_seconds),
        )
        session.add(entry)
        await session.flush()
        return "in_flight", None

    async def complete(self, scope: str, response_hash: str) -> None:
        session = get_current_session()
        await session.execute(
            text("""
                UPDATE idempotency_keys
                SET status = 'completed', response_hash = :response_hash
                WHERE scope = :scope
            """),
            {"scope": scope, "response_hash": response_hash},
        )


class PublishJobRepository:
    async def create_job(
        self,
        *,
        umkm_id: object,
        content_id: object,
        platform: str,
        scheduled_at: object | None,
        created_by: object,
        job_id: UUID,
    ) -> None:
        session = get_current_session()
        row = PublishJob(
            id=job_id,
            content_id=content_id,
            platform=platform,
            status="queued",
            scheduled_at=scheduled_at,
        )
        session.add(row)
        await session.flush()

    async def find_job(self, umkm_id: object, content_id: object, platform: str) -> object | None:
        session = get_current_session()
        result = await session.execute(
            select(PublishJob).where(
                PublishJob.content_id == content_id,
                PublishJob.platform == platform,
            )
        )
        return result.scalar_one_or_none()
