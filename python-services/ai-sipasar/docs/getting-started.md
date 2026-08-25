# Getting Started

> From zero to a running generation in ~5 minutes (with Docker) or ~10 minutes (local Postgres).

---

## 1. Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| Python | 3.12+ | `python --version` |
| PostgreSQL | 16 + `pgvector` | or Docker via testcontainers |
| `OPENAI_API_KEY` | — | required for generation |
| Cloudinary | optional | required for media uploads |
| Docker | optional | for integration tests & image build |

---

## 2. Install

```bash
git clone <repo> && cd AIC
python -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"
cp .env.example .env   # fill DATABASE_URL, OPENAI_API_KEY, JWT_SECRET
```

> `.env` never goes into git. Production uses a secret manager.

---

## 3. Configure (minimal)

Edit `.env`:

```env
DATABASE_URL=postgresql+asyncpg://sipromo:sipromo@localhost:5432/sipromo
JWT_SECRET=change-me-in-production   # must change when APP_ENV != development
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
EMBEDDING_DIM=768
```

Full reference: [Configuration](configuration.md) and [`src/sipromo/bootstrap/settings.py:1`](../src/sipromo/bootstrap/settings.py).

---

## 4. Database

```bash
# Create DB (if local Postgres)
createdb sipromo
alembic upgrade head
alembic check          # must report "no drift"
```

Migration notes:

* `0001_legacy_baseline` **no-ops** when legacy Neon tables already exist — safe to adopt existing data.
* `0002`/`0003` create only new structures: `knowledge_*`, `generation_*`, `content_*`, `sipromo_jobs`, `umkm_memberships`, `idempotency_keys` plus RLS policies.

See [Deployment](deployment.md) and [Rollback Plan](rollback_plan.md) for production procedure (Neon branch → staging → additive migrate).

---

## 5. Run

```bash
uvicorn main:create_app --factory --reload --port 8000
# or
python -m uvicorn main:create_app --factory --reload
```

Open:

* Swagger UI → http://localhost:8000/docs  (Authorize with `Bearer <JWT>`)
* ReDoc → http://localhost:8000/redoc
* OpenAPI JSON → http://localhost:8000/openapi.json
* Health → http://localhost:8000/api/v1/health/live

> When `AUTH_ENABLED=false` the server uses the fixed development actor
> (`auth_disabled_user_id` / `auth_disabled_umkm_id`) so you can try `/docs` without a token.
> Never use this in production (`src/sipromo/presentation/api/dependencies.py:23`).

---

## 6. Try the API

### 6.1 Health (no auth)

```bash
curl http://localhost:8000/api/v1/health/live
# {"status":"ok"}
curl http://localhost:8000/api/v1/health/ready
```

### 6.2 Seed knowledge

```bash
python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed
```

Each file's directory name (or `type__` prefix) maps to `document_type` (`brand_guide`, `product_catalog`, `faq`, `campaign_example`, `policy`, `other`). Content is chunked → embedded (`EMBEDDING_DIM=768`) → stored in `knowledge_chunks` with `vector(768)`.

### 6.3 Generate a promotion

```bash
curl -X POST http://localhost:8000/api/v1/promotions/generate \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "objective": "conversion",
    "content_type": "social_media",
    "platform": "instagram",
    "product_ids": ["<product-uuid>"],
    "tone": "friendly",
    "language": "id",
    "key_message": "Produk lokal praktis untuk hadiah",
    "call_to_action": "Lihat katalog",
    "constraints": ["Jangan menyatakan diskon"],
    "include_market_context": true
  }'
```

Response `201`:

```json
{
  "content_id": "…",
  "generation_run_id": "…",
  "status": "draft",
  "version": 1,
  "content": { "title":"…", "primary_copy":"…", "caption":"…", "hashtags":[], "call_to_action":"…" },
  "evidence": [],
  "warnings": [],
  "requires_human_review": true
}
```

### 6.4 Approve → publish

```bash
curl -X POST http://localhost:8000/api/v1/promotions/<id>/approval \
  -H "Authorization: Bearer $JWT" -H "Content-Type: application/json" \
  -d '{"decision":"approved"}'

curl -X POST http://localhost:8000/api/v1/promotions/<id>/publish \
  -H "Authorization: Bearer $JWT" -H "Content-Type: application/json" \
  -d '{"platform":"instagram"}'
```

Publishing without approval returns `409 APPROVAL_REQUIRED`.

---

## 7. Quality gates (CI)

```bash
ruff check src/ migrations/ tests/ scripts/
ruff format --check src/ migrations/ tests/ scripts/
mypy src/sipromo --ignore-missing-imports
python -m pytest tests/unit tests/integration -q
```

Integration tests spin up `pgvector/pgvector:pg16` via **testcontainers** — no local Postgres required.

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `AI provider not configured` at startup | `OPENAI_API_KEY` empty and `APP_ENV != test` | Set `OPENAI_API_KEY` or run with `APP_ENV=test` |
| `pgvector` error on migrate | Postgres without `vector` extension | Use `pgvector/pgvector:pg16` image |
| `401 UNAUTHENTICATED` in `/docs Try it out` | Missing Bearer token | Click Authorize → `Bearer <JWT>`; or set `AUTH_ENABLED=false` for dev |
| `413 FileTooLarge` on upload | `UPLOAD_MAX_BYTES` exceeded | Lower file size or raise limit in `.env` |
| Empty RAG results | No seeded chunks or `RAG_MIN_SCORE` too high | Seed via `scripts/ingest_seed_knowledge.py`; check [Evaluation](evaluation_report.md) |

---

## Next steps

* [Architecture](architecture.md) — layers, data flow, ADRs.
* [API Reference](api-reference.md) — endpoints, auth, errors, idempotency.
* [Configuration](configuration.md) — all env vars.
* [Deployment](deployment.md) — Docker + Neon + rollback.
