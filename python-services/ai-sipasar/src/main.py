"""FastAPI application factory — professional OpenAPI / docs configuration."""

from __future__ import annotations

import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.openapi.utils import get_openapi
from fastapi.responses import JSONResponse

from sipasar.api.routes_analysis import router as analytics_router
from sipasar.api.routes_health import router as analytics_health_router
from sipasar.providers.places_provider import PlacesProviderUnavailableError
from sipromo.bootstrap.container import Container
from sipromo.bootstrap.settings import get_settings
from sipromo.infrastructure.observability.telemetry import (
    get_request_id,
    new_request_id,
    set_actor,
    set_request_id,
    setup_logging,
)
from sipromo.presentation.api import v1
from sipromo.presentation.api.exception_handlers import register_exception_handlers

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# OpenAPI metadata — served at /docs (Swagger UI), /redoc, /openapi.json
# ---------------------------------------------------------------------------

_API_DESCRIPTION = """
## SiPasar Platform — Promotion and Market Analytics for UMKM

Satu API untuk **SiPromo** (pembuatan promosi kontekstual) dan
**SiPasar Analytics** (analisis kompetitor, geodemografi, dan potensi pasar).

### SiPromo

RAG + tool-calling backend that generates **context-aware promotion copy** for
Indonesian small businesses (UMKM), grounded in the tenant's own knowledge base
with deterministic claim validation and human-in-the-loop approval.

### Key guarantees

* **Tenant-isolated** — every query is filtered by `umkm_id` from the JWT and
  enforced again by Postgres Row-Level Security.
* **Grounded generation** — hybrid RAG (pgvector HNSW + FTS + RRF) + 7 read-only
  tools; the model never writes directly — drafts are persisted by the server.
* **Deterministic policy** — currency, dates, discounts, superlatives and
  evidence citations are validated; `requires_human_review` is always `true`.
* **Idempotent** — mutating endpoints honour `Idempotency-Key`.

### Quick start

```bash
# 1. Health check (no auth)
curl http://localhost:8000/api/v1/health/live

# 2. Generate (JWT required)
curl -X POST http://localhost:8000/api/v1/promotions/generate \\
  -H "Authorization: Bearer <JWT>" \\
  -H "Content-Type: application/json" \\
  -H "Idempotency-Key: <uuid>" \\
  -d '{"objective":"conversion","content_type":"social_media",
       "platform":"instagram","product_ids":["..."],
       "tone":"friendly","key_message":"Produk lokal praktis untuk hadiah"}'
```

Full guides: `docs/index.md` → `docs/api/overview.md`.

### SiPasar Analytics

Gunakan `POST /v1/analysis/run` untuk menjalankan analisis pasar. Google Places
bersifat opsional; tanpa API key, modul menggunakan fallback OpenStreetMap.
"""

_OPENAPI_TAGS = [
    {
        "name": "health",
        "description": (
            "Liveness, readiness and dependency checks. "
            "No auth for `live`/`ready`; `dependencies` is owner-only."
        ),
    },
    {
        "name": "knowledge",
        "description": (
            "Tenant-scoped knowledge base — upload, list, retrieve and archive "
            "brand guides, catalogs, FAQs, campaign examples and policies. "
            "RAG ingestion is async (202)."
        ),
    },
    {
        "name": "promotions",
        "description": (
            "Promotion lifecycle — **generate → revise → approve → publish**. "
            "Generation is RAG + tool-calling (OpenAI), always "
            "`requires_human_review=true`."
        ),
    },
    {
        "name": "approvals",
        "description": (
            "Approval history for a content item. "
            "Decisions: `approved` / `rejected` / `changes_requested`."
        ),
    },
    {
        "name": "assets",
        "description": (
            "Media uploads (logos, product photos) via Cloudinary. "
            "Server-side upload only — secrets never reach the client."
        ),
    },
    {
        "name": "analysis",
        "description": "Analisis kompetitor, geodemografi, dan potensi pasar SiPasar.",
    },
    {
        "name": "analytics-health",
        "description": "Status konfigurasi provider SiPasar Analytics.",
    },
]

_SERVERS = [
    {"url": "http://localhost:8000", "description": "Local development"},
    {"url": "https://api.sipromo.example.com", "description": "Production (example)"},
]


@asynccontextmanager
async def lifespan(app: FastAPI):
    container: Container = app.state.container
    if container.settings.app_env not in {"test", "development"}:
        container.validate_ai_configuration()
    container.build_use_cases()
    logger.info(
        "application_started",
        extra={
            "app": container.settings.app_name,
            "env": container.settings.app_env,
            "openai": container.settings.openai_model,
            "tools": len(container.tool_registry._tools),  # type: ignore[attr-defined]
        },
    )
    yield
    await container.dispose()


def create_app() -> FastAPI:
    setup_logging(get_settings().log_level)
    settings = get_settings()
    container = Container(settings)
    app = FastAPI(
        title="SiPasar Platform API",
        description=_API_DESCRIPTION,
        version="1.0.0",
        summary="Unified promotion generation and market analytics for UMKM",
        contact={
            "name": "SiPromo Team — AIC Hackathon",
            "url": "https://github.com/anomalyco/opencode",
        },
        license_info={
            "name": "MIT",
            "url": "https://opensource.org/licenses/MIT",
        },
        terms_of_service="https://github.com/anomalyco/opencode#terms",
        openapi_tags=_OPENAPI_TAGS,
        servers=_SERVERS,
        # Swagger / ReDoc — professional setup
        docs_url="/docs",
        redoc_url="/redoc",
        openapi_url="/openapi.json",
        swagger_ui_parameters={
            "persistAuthorization": True,
            "filter": True,
            "displayRequestDuration": True,
            "defaultModelsExpandDepth": 2,
            "defaultModelExpandDepth": 2,
            "docExpansion": "list",
            "showExtensions": True,
            "tryItOutEnabled": True,
        },
        lifespan=lifespan,
    )
    app.state.container = container
    app.state.settings = settings

    # --- Custom OpenAPI — inject BearerAuth + global error docs -----------
    def _custom_openapi():
        if app.openapi_schema:
            return app.openapi_schema
        schema = get_openapi(
            title=app.title,
            version=app.version,
            description=app.description,
            routes=app.routes,
            tags=app.openapi_tags,
            servers=app.servers,
            contact=app.contact,
            license_info=app.license_info,
            terms_of_service=app.terms_of_service,
        )
        # Security scheme — appears as "Authorize" button in Swagger UI.
        schema.setdefault("components", {}).setdefault("securitySchemes", {})["BearerAuth"] = {
            "type": "http",
            "scheme": "bearer",
            "bearerFormat": "JWT",
            "description": (
                "JWT access token. Obtain via login flow; send as `Authorization: Bearer <token>`. "
                "When `AUTH_ENABLED=false` (development), the docs can be tried without a token "
                "— the server uses the fixed `auth_disabled_*` actor."
            ),
        }
        # Attach security requirement to every protected route.
        # Health live/ready are public; everything else needs BearerAuth.
        public_prefixes = (
            "/api/v1/health/live",
            "/api/v1/health/ready",
            "/v1/health",
            "/v1/analysis",
            "/docs",
            "/redoc",
            "/openapi.json",
        )
        for path, path_item in schema.get("paths", {}).items():
            if any(path.startswith(p) for p in public_prefixes):
                continue
            for operation in path_item.values():
                if not isinstance(operation, dict):
                    continue
                # Don't overwrite explicit security (if any route already declares it)
                if "security" not in operation:
                    operation["security"] = [{"BearerAuth": []}]
                # Add common error responses if not already documented
                responses = operation.setdefault("responses", {})
                responses.setdefault(
                    "401",
                    {
                        "description": "Unauthenticated — missing or invalid Bearer token",
                        "content": {
                            "application/json": {
                                "schema": {"$ref": "#/components/schemas/HTTPValidationError"},
                                "example": {
                                    "error": {
                                        "code": "UNAUTHENTICATED",
                                        "message": "Missing bearer token",
                                        "request_id": "01H...",
                                    }
                                },
                            }
                        },
                    },
                )
                responses.setdefault(
                    "403",
                    {
                        "description": "Forbidden — tenant membership / role check failed",
                        "content": {
                            "application/json": {
                                "example": {
                                    "error": {
                                        "code": "FORBIDDEN",
                                        "message": "User is not a member of the tenant",
                                        "request_id": "01H...",
                                    }
                                }
                            }
                        },
                    },
                )
        app.openapi_schema = schema
        return app.openapi_schema

    app.openapi = _custom_openapi  # type: ignore[method-assign]

    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_methods=["*"],
        allow_headers=["*"],
    )

    @app.middleware("http")
    async def request_context_middleware(request: Request, call_next):
        request_id = request.headers.get("X-Request-ID") or new_request_id()
        set_request_id(request_id)
        set_actor(None)
        response = await call_next(request)
        response.headers["X-Request-ID"] = request_id
        return response

    register_exception_handlers(app)

    @app.exception_handler(PlacesProviderUnavailableError)
    async def analytics_provider_error_handler(
        request: Request, exc: PlacesProviderUnavailableError
    ) -> JSONResponse:
        logger.warning("analytics places provider unavailable: %s", exc)
        return JSONResponse(
            status_code=503,
            content={
                "error": {
                    "code": "COMPETITOR_PROVIDER_UNAVAILABLE",
                    "message": "Competitor data provider is temporarily unavailable",
                    "request_id": get_request_id(),
                }
            },
        )

    app.include_router(v1.router, prefix=settings.api_v1_prefix)
    app.include_router(analytics_router)
    app.include_router(analytics_health_router)

    @app.get("/", include_in_schema=False)
    async def root() -> dict[str, object]:
        return {
            "service": "SiPasar Platform",
            "modules": ["sipromo", "sipasar-analytics"],
            "docs": "/docs",
            "health": {
                "platform": f"{settings.api_v1_prefix}/health/live",
                "analytics": "/v1/health",
            },
        }

    return app


app = create_app()
