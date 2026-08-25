"""FastAPI dependencies: settings, container, authenticated actor with
database-verified tenant membership, and idempotency guard."""

from __future__ import annotations

import hashlib
import json
from collections.abc import AsyncIterator
from uuid import UUID

from fastapi import Header, HTTPException, Request

from sipromo.application.dto.promotion_requests import AuthenticatedActor
from sipromo.bootstrap.container import Container
from sipromo.domain.exceptions import InfrastructureError


def get_container(request: Request) -> Container:
    return request.app.state.container


async def get_actor(
    request: Request,
    authorization: str | None = Header(default=None),
) -> AsyncIterator[AuthenticatedActor]:
    """Authenticate, verify tenant membership, and open the request
    transaction (session + RLS tenant context) for the route's lifetime.

    With auth disabled (auth_enabled=false) the fixed development actor is
    used so the API can be exercised without a bearer token.
    """
    container: Container = request.app.state.container
    settings = container.settings
    if not settings.auth_enabled:
        container.unit_of_work.set_tenant(UUID(settings.auth_disabled_umkm_id))
        container.unit_of_work.set_user(UUID(settings.auth_disabled_user_id))
        async with container.unit_of_work.begin():
            yield AuthenticatedActor(
                user_id=UUID(settings.auth_disabled_user_id),
                umkm_id=UUID(settings.auth_disabled_umkm_id),
                role=settings.auth_disabled_role,
            )
        return
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(
            status_code=401, detail=_error("UNAUTHENTICATED", "Missing bearer token")
        )
    token = authorization.split(" ", 1)[1].strip()
    try:
        claims = container.jwt_service.decode(token)
    except InfrastructureError as exc:
        raise HTTPException(status_code=401, detail=_error(exc.error_code, exc.message)) from exc

    user_id = claims.user_id
    umkm_id = claims.umkm_id
    role = claims.role

    if umkm_id is None or role is None:
        async with container.session_factory.session() as session:
            membership = await container.membership_repo.get_membership_for_user(
                user_id, session=session
            )
        if membership is None:
            raise HTTPException(
                status_code=403,
                detail=_error("FORBIDDEN", "User is not a member of any tenant"),
            )
        umkm_id, role = membership

    # Set tenant context for the request lifetime; the transaction opened below
    # applies SET app.current_umkm_id (RLS defense in depth).
    container.unit_of_work.set_tenant(umkm_id)
    container.unit_of_work.set_user(user_id)

    async with container.unit_of_work.begin():
        membership_role = await container.membership_repo.get_membership(umkm_id, user_id)
        if membership_role is None:
            raise HTTPException(
                status_code=403,
                detail=_error("FORBIDDEN", "User is not a member of the tenant"),
            )
        yield AuthenticatedActor(
            user_id=user_id,
            umkm_id=umkm_id,
            role=membership_role,
        )


def _error(code: str, message: str) -> dict:
    return {"error": {"code": code, "message": message}}


class IdempotencyGuard:
    """Enforces the Idempotency-Key contract for mutating endpoints."""

    def __init__(
        self, container: Container, actor: AuthenticatedActor, key: str, payload: dict
    ) -> None:
        self._container = container
        self._actor = actor
        self._key = key
        self._scope = f"{actor.user_id}:{actor.umkm_id}:{key}"
        self._payload_hash = hashlib.sha256(
            json.dumps(payload, sort_keys=True, default=str).encode("utf-8")
        ).hexdigest()

    async def begin(self) -> str | None:
        """Return stored response hash if this key already completed; else None."""
        status, response_hash = await self._container.idempotency_repo.get_or_create(
            scope=self._scope,
            request_hash=self._payload_hash,
            ttl_seconds=3600,
        )
        if status == "completed" and response_hash:
            return response_hash
        return None

    async def complete(self, response: dict) -> None:
        response_hash = hashlib.sha256(
            json.dumps(response, sort_keys=True, default=str).encode("utf-8")
        ).hexdigest()
        await self._container.idempotency_repo.complete(self._scope, response_hash)


async def idempotency_guard_factory(
    container: Container,
    actor: AuthenticatedActor,
    key: str,
    payload: dict,
) -> IdempotencyGuard:
    return IdempotencyGuard(container, actor, key, payload)


def error_payload(code: str, message: str) -> dict:
    return _error(code, message)
