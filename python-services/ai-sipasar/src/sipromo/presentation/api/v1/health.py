"""Health endpoints: live / ready / dependencies (section 24.2)."""

from __future__ import annotations

from fastapi import APIRouter, Depends, Request
from fastapi.responses import JSONResponse

from sipromo.application.dto.promotion_requests import AuthenticatedActor
from sipromo.bootstrap.container import Container
from sipromo.presentation.api.dependencies import get_actor

router = APIRouter(tags=["health"])


@router.get(
    "/health/live",
    summary="Liveness probe",
    description='Returns `{"status":"ok"}` if the process is alive. No auth, no DB check.',
)
async def health_live() -> dict:
    return {"status": "ok"}


@router.get(
    "/health/ready",
    summary="Readiness probe",
    description="Checks DB reachability and migration state. Returns `503 degraded` when unreachable.",
)
async def health_ready(request: Request) -> JSONResponse:
    container: Container = request.app.state.container
    db_ok = await container.unit_of_work.ping()
    if db_ok:
        return JSONResponse({"status": "ok", "database": "reachable"})
    return JSONResponse(
        {"status": "degraded", "database": "unreachable"},
        status_code=503,
    )


@router.get(
    "/health/dependencies",
    summary="Dependency status (owner only)",
    description="Reports `database/openai/cloudinary` as `configured`/`not_configured`/`degraded` without leaking secrets. Requires `owner` role.",
)
async def health_dependencies(
    request: Request, actor: AuthenticatedActor = Depends(get_actor)
) -> JSONResponse:
    """Admin-only detail about external providers, without secrets."""
    if actor.role != "owner":
        return JSONResponse({"status": "forbidden"}, status_code=403)
    container: Container = request.app.state.container
    components: dict[str, str] = {
        "database": "configured",
        "openai": "configured" if container.settings.openai_configured else "not_configured",
        "cloudinary": (
            "configured" if container.settings.cloudinary_configured else "not_configured"
        ),
    }
    status = "ok" if container.settings.openai_configured else "degraded"
    return JSONResponse({"status": status, "components": components})
