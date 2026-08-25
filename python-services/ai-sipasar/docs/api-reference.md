# API Reference

> **Base path:** `API_V1_PREFIX` (default `/api/v1`)  
> **Interactive docs:** `/docs` (Swagger UI) · `/redoc` (ReDoc) · `/openapi.json`  
> **Factory:** `src/main.py:113` (`create_app`) — BearerAuth, tags, servers, error docs injected via `_custom_openapi`.

---

## 1. Conventions

### 1.1 Authentication

* Scheme: **Bearer JWT** — `Authorization: Bearer <token>` (`src/sipromo/presentation/api/dependencies.py:22`).
* JWT claims: `user_id`, `umkm_id` (optional — resolved via `umkm_memberships`), `role` (`owner`/`staff`/`viewer`).
* Every mutating/read endpoint verifies tenant membership inside the request transaction (`MembershipRepositoryImpl`); `umkm_id` for generation, knowledge and content is **never** taken from the body.
* `AUTH_ENABLED=false` uses the fixed actor (`auth_disabled_*` in `.env`) — development only.
* Swagger UI: click **Authorize → BearerAuth** (`src/main.py:168`). `persistAuthorization:true` keeps the token across reloads.

### 1.2 Tenant isolation

* `umkm_id` filter on every repository query + Postgres **RLS FORCE** on `knowledge_documents`, `knowledge_chunks`, `generation_runs` (`set_config('app.current_umkm_id', ..., true)` per transaction).
* Integration test asserts 0 leakage with a non-superuser role.

### 1.3 Error envelope

All errors share one shape (never stack traces / secrets):

```json
{
  "error": {
    "code": "PRODUCT_NOT_FOUND",
    "message": "Produk tidak ditemukan",
    "request_id": "01H...",
    "details": [{ "loc": "body.product_ids", "msg": "…" }]
  }
}
```

`X-Request-ID` is echoed on every response (`src/main.py:239`). Mapping: `src/sipromo/presentation/api/exception_handlers.py:32`.

| HTTP | `code` | When |
|---|---|---|
| 400 | `BAD_REQUEST` / `VALIDATION_ERROR` | Pydantic / domain validation |
| 401 | `UNAUTHENTICATED` | missing/invalid Bearer |
| 403 | `FORBIDDEN` / `TENANT_MISMATCH` | not a member of the tenant, wrong role |
| 404 | `DOCUMENT_NOT_FOUND` / `CONTENT_NOT_FOUND` | unknown id (tenant-scoped) |
| 409 | `IDEMPOTENCY_REPLAY` / `APPROVAL_REQUIRED` / `ALREADY_DECIDED` | replay, publish without approval |
| 413 | `FILE_TOO_LARGE` | `UPLOAD_MAX_BYTES` |
| 415 | `UNSUPPORTED_FILE_TYPE` | MIME / extension |
| 422 | `PRODUCT_NOT_FOUND` / `CLAIM_VIOLATION` | brief refs unknown product, ungrouded claim |
| 503 | `AI_QUOTA_EXHAUSTED` / `CONFIGURATION_ERROR` | OpenAI 429 / missing key |

### 1.4 Idempotency

Mutating `POST` endpoints accept `Idempotency-Key` (scope = `user_id:umkm_id:key` + `sha256(body)`). Replay returns `409 IDEMPOTENCY_REPLAY`. Stored TTL = 1h (`src/sipromo/presentation/api/dependencies.py:95`).

### 1.5 Rate / budget limits

* Tool loop: `AI_MAX_TOOL_ROUNDS=6`, `AI_MAX_TOOL_CALLS=16`, `LLM_RUN_MAX_TOTAL_TOKENS=60000`.
* Upload: `UPLOAD_MAX_BYTES=10 MiB` (knowledge), `5 MiB` (assets). See [Configuration](configuration.md).

---

## 2. Health

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/api/v1/health/live` | none | Process alive — no dependencies. |
| `GET` | `/api/v1/health/ready` | none | DB `ping()` + `alembic check` drift. `503` if degraded. |
| `GET` | `/api/v1/health/dependencies` | owner | `database/openai/cloudinary` as `configured`/`not_configured`/`degraded` (no secrets). |

Source: `src/sipromo/presentation/api/v1/health.py:1`.

---

## 3. Knowledge — `tag: knowledge`

Tenant-scoped RAG corpus (`knowledge_documents` → `knowledge_chunks` with `vector(768)` HNSW + FTS).

### `POST /knowledge/documents` — upload

* **Auth:** any member. **Status:** `202 Accepted`.
* **Content-Type:** `multipart/form-data`
  * `file` (binary, required)
  * `title` (string, required, ≤255)
  * `document_type` (`brand_guide` | `product_catalog` | `faq` | `campaign_example` | `policy` | `other`, default `other`) — `src/sipromo/presentation/api/v1/knowledge.py:26`
* **Flow:** MIME + size → SHA-256 → Cloudinary (if configured) → `knowledge_documents(pending)` → extract → chunk (500–800 tokens, 80–120 overlap) → embed → bulk insert → `ready` (or `failed` + compensating delete).
* **Idempotent:** `UNIQUE (umkm_id, checksum_sha256)` — duplicate returns existing.

```bash
curl -X POST http://localhost:8000/api/v1/knowledge/documents \
  -H "Authorization: Bearer $JWT" \
  -F file=@brand_guide.pdf -F title="Brand Guide 2026" -F document_type=brand_guide
```

### `GET /knowledge/documents`

* **Auth:** member. Filters: `?status=ready&document_type=brand_guide`.
* Returns tenant-only rows; enriches with `chunk_count`.

### `GET /knowledge/documents/{id}`

* Tenant-scoped fetch; `404 DOCUMENT_NOT_FOUND` if foreign.

### `DELETE /knowledge/documents/{id}`

* `?hard=false` → `archived`; `?hard=true` → hard delete + Cloudinary compensating delete (`src/sipromo/application/use_cases/ingest_knowledge.py`).

Source: `src/sipromo/presentation/api/v1/knowledge.py:43`.

---

## 4. Promotions — `tag: promotions`

Lifecycle: **generate → revise → approve → publish**. All writes are server-initiated; the model cannot persist.

### 4.1 `POST /promotions/generate` — `201`

* **Auth:** member. **Body:** `PromotionBrief` (`src/sipromo/domain/value_objects/promotion_brief.py:16`).

```json
{
  "objective": "conversion",
  "content_type": "social_media | ad_copy | blog_post | email",
  "platform": "instagram | facebook | generic",
  "product_ids": ["uuid"],
  "target_audience": "Pekerja muda di Surabaya",
  "tone": "friendly | professional | playful | premium | educational",
  "language": "id | en",
  "key_message": "Produk lokal praktis untuk hadiah",
  "call_to_action": "Lihat katalog",
  "constraints": ["Jangan menyatakan diskon"],
  "include_market_context": true,
  "include_business_performance": false
}
```

* Validates product ownership; creates `generation_runs(started)`; hybrid retrieval (vector `RAG_TOP_K_VECTOR=12` + lexical `12` → RRF `K=60` → diversity ≤3/doc → `RAG_FINAL_K=8`, `RAG_MIN_SCORE=0.55`, token budget `RAG_MAX_CONTEXT_TOKENS=6000`); tool loop; `PromotionOutput` parse; deterministic policy; persist `content_assets(draft)` + `content_revisions(1)` + `content_sources` + `generation_runs(completed)` atomically.
* **Headers:** `Idempotency-Key` supported. **Errors:** `422 PRODUCT_NOT_FOUND`, `503 AI_QUOTA_EXHAUSTED`.

Response `PromotionDraftDTO` (`src/sipromo/application/dto/promotion_responses.py:14`):

```json
{
  "content_id": "uuid",
  "generation_run_id": "uuid",
  "status": "draft",
  "version": 1,
  "title": "…",
  "primary_copy": "…",
  "caption": "…",
  "hashtags": ["…"],
  "call_to_action": "…",
  "visual_brief": "…",
  "target_audience_summary": "…",
  "rationale": ["…"],
  "claims": ["…"],
  "evidence": [{"evidence_id":"…","source_kind":"rag_chunk|tool_result|user_input","source_ref":"…","supported_claims":[]}],
  "warnings": ["…"],
  "requires_human_review": true,
  "image_url": null
}
```

> `requires_human_review` is always `true` in MVP — no auto-publish.

Source: `src/sipromo/presentation/api/v1/promotions.py:52`, `src/sipromo/application/use_cases/generate_promotion.py`.

### 4.2 `GET /promotions/{content_id}`

Returns latest `content_assets` + `content_revisions` payload for the tenant.

### 4.3 `POST /promotions/{id}/revisions` — `201`

```json
{ "feedback": "…", "edited_payload": {}, "change_reason": "…" }
```

Appends a new `content_revisions` row — history is never overwritten.

### 4.4 `POST /promotions/{id}/approval` — `ApprovalDTO`

```json
{ "decision": "approved | rejected | changes_requested", "notes": "…" }
```

Only `owner`/`staff` (policy) may approve; already-decided returns `409`.

### 4.5 `POST /promotions/{id}/publish` — `201 PublishJobDTO`

```json
{ "platform": "instagram", "scheduled_at": "2026-08-23T10:00:00Z" }
```

Requires an `approved` revision; creates `sipromo_jobs` row (`FOR UPDATE SKIP LOCKED` claim). Returns:

```json
{ "job_id":"…", "content_id":"…", "platform":"instagram", "status":"queued", "scheduled_at":"…" }
```

Source: `src/sipromo/presentation/api/v1/promotions.py:149`.

---

## 5. Approvals — `tag: approvals`

### `GET /promotions/{content_id}/approvals`

Lists decisions for a content item (tenant-scoped join via `ContentAsset.umkm_id`).

Source: `src/sipromo/presentation/api/v1/approvals.py:22`.

---

## 6. Assets — `tag: assets`

### `POST /assets/upload?kind=logo|product` — images only, `5 MiB`

Server-side Cloudinary upload to `sipromo/{umkm_id}/{kind}/{uuid}` (`resource_type=image`). Returns `{ asset_id, public_id, secure_url, format, bytes, width, height }`. Fails with `503` when Cloudinary not configured.

Source: `src/sipromo/presentation/api/v1/assets.py:25`.

---

## 7. OpenAPI

* JSON: `GET /openapi.json`
* Swagger UI: `GET /docs` — `persistAuthorization`, `filter`, `displayRequestDuration`, `tryItOutEnabled` (`src/main.py:137`).
* ReDoc: `GET /redoc`
* Bearer scheme `BearerAuth` is injected globally (`src/main.py:152`); public paths (`/health/live`, `/health/ready`) are excluded.

Regenerate for SDKs:

```bash
python -c "from src.main import create_app; import json; print(json.dumps(create_app().openapi(), indent=2))" > openapi.json
```

---

## 8. Tool registry (model-facing, not HTTP)

Allowlisted tools — `src/sipromo/infrastructure/tools/registry.py`, `read_tools.py`, `write_tools.py`:

*Read (model-callable):* `get_business_profile`, `get_products`, `get_inventory_eligibility`, `get_market_summary`, `get_competitor_summary`, `get_sales_summary`, `search_brand_knowledge`  
*Write (server-only):* `save_promotion_draft` (denied in generation loop), `create_publish_job` (application use case).

All arguments Pydantic-validated; `umkm_id` injected from auth; output sanitized via `PRIVATE_FIELD_NAMES`.
