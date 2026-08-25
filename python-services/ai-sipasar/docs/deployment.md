# Deployment

> Non-root, multi-stage Docker · single process per container · additive migrations · Neon PITR · Cloudinary immutable assets.

---

## 1. Build

```dockerfile
# Dockerfile (already in repo — non-root, multi-stage)
FROM python:3.12-slim AS builder
WORKDIR /app
COPY pyproject.toml ./
RUN pip install --no-cache-dir -e .

FROM python:3.12-slim
RUN useradd --create-home appuser
WORKDIR /app
COPY --from=builder /usr/local /usr/local
COPY src ./src
COPY migrations ./migrations
COPY alembic.ini ./
USER appuser
CMD ["uvicorn", "main:create_app", "--factory", "--host", "0.0.0.0", "--port", "8000"]
```

```bash
docker build -t sipromo:0.1.0 .
docker run --env-file .env -p 8000:8000 sipromo:0.1.0
# verify
curl http://localhost:8000/api/v1/health/live
curl http://localhost:8000/api/v1/health/ready
```

Image checks: `python:3.12-slim`, no secrets in layers, health check via `HEALTHCHECK CMD curl -f http://localhost:8000/api/v1/health/live`.

---

## 2. Database — Neon / Postgres 16 + pgvector

### 2.1 Migration strategy (additive, safe for existing data)

1. **Branch** Neon (PITR) or snapshot.
2. Run migrations on the **staging branch**:

   ```bash
   alembic upgrade head
   alembic check   # must say "no drift"
   ```
3. Test backward compatibility — old code must still run against `0002`/`0003` (no dropped columns).
4. Deploy **migration** to production, then **application** image.
5. Feature-flag cutover: generation/approval/publish are separate routes — disable `publish` at the gateway if needed.
6. Monitor `generation success rate`, `p50/p95 latency`, `quota errors`.

### 2.2 Alembic commands

| Command | Notes |
|---|---|
| `alembic upgrade head` | Apply all. `0001_legacy_baseline` no-ops if legacy tables exist. |
| `alembic downgrade 0002_knowledge_and_trace` | Drops knowledge/trace/RLS; generation data lost (acceptable for rollback). |
| `alembic downgrade base` | Leaves legacy Neon schema untouched (0001 is introspect-only). |
| `alembic check` | CI gate — fails on drift. |

Full rollback table: [Rollback Plan](rollback_plan.md).

---

## 3. Environment in production

* Secrets via **platform secret manager** (not `.env` file).
* Required: `DATABASE_URL`, `JWT_SECRET` (non-default), `OPENAI_API_KEY`.
* Optional: `CLOUDINARY_*` (uploads disabled if missing — fails loudly).
* `APP_ENV=production` — enables `validate_ai_configuration()` (`src/sipromo/bootstrap/container.py:135`).

---

## 4. Health & observability

| Endpoint | Checks | Auth |
|---|---|---|
| `/health/live` | process alive | none |
| `/health/ready` | DB reachable + migration version | none |
| `/health/dependencies` | `openai`/`cloudinary` as `configured`/`not_configured`/`degraded` | `owner` only, no secrets |

Logging: `structlog` + OpenTelemetry (`src/sipromo/infrastructure/observability/telemetry.py`). Every request logs `request_id`, `trace_id`, obfuscated `user_id`/`umkm_id`, `generation_run_id`, model, retrieval count/latency, tool `name/status/duration`, validation result.

**Never log:** raw prompt (unless synthetic debug mode), full tool payloads, secrets. Use hash/metadata.

Metrics to dashboard (blueprint §19):

* `generation_success_rate`, `invalid_structured_output_rate`, `tool_failure_rate`, `retrieval_empty_rate`, `unsupported_claim_rate`, `approval_rate`, `revision_count`, `time_to_approved_draft`, `quota_errors`, `p50/p95 per stage`.

---

## 5. Scaling & jobs

* **Horizontal workers** safe via `FOR UPDATE SKIP LOCKED` job claiming (`sipromo_jobs`, `src/sipromo/infrastructure/db/repositories/misc_repositories.py`).
* No external broker — Postgres is the queue (ADR-005). For larger scale, replace `PublishJobRepository` behind its port.
* Ingestion is idempotent by `(umkm_id, checksum_sha256)`; re-run `python -m scripts.ingest_seed_knowledge` safely.

---

## 6. Rollback

Detailed in [Rollback Plan](rollback_plan.md) — summary:

1. Freeze deploys; keep serving (read/search still safe).
2. If generation only is broken → disable that route at gateway.
3. If data corruption → `alembic downgrade` to prior revision → Neon PITR restore → `alembic upgrade head` → re-ingest.
4. Verify: `alembic check`, `health/ready`, `scripts/run_rag_evaluation.py` (tenant leakage must be `0`).

---

## 7. Checklist (Definition of Done per PR — blueprint §30)

- [ ] Scope small, single purpose
- [ ] Domain has no vendor imports
- [ ] Unit + integration (pgvector, not SQLite) tests added
- [ ] Migration has sensible `downgrade` (except `vector` extension)
- [ ] OpenAPI updated (`/openapi.json` matches `docs/api-reference.md`)
- [ ] No secret / production data in diff
- [ ] `ruff check` + `mypy src/sipromo` pass
- [ ] Security impact reviewed (IDOR, injection, leakage)
- [ ] Observability fields added
- [ ] ADR added if architectural

---

## 8. References

* App factory + OpenAPI: `src/main.py:1`
* Settings: `src/sipromo/bootstrap/settings.py:1`, `src/sipromo/bootstrap/container.py:1`
* Migrations: `migrations/versions/`, `alembic.ini`
* Scripts: `scripts/ingest_seed_knowledge.py`, `scripts/run_rag_evaluation.py`
