# Configuration

> All settings are env-driven via `src/sipromo/bootstrap/settings.py:6` (`Pydantic Settings`, `.env` file, `extra=ignore`). No secrets in git.

---

## 1. Environment file

```bash
cp .env.example .env   # then fill secrets
```

`.env.example` is canonical — `src/sipromo/bootstrap/settings.py:16` lists every key with defaults.

---

## 2. Variables

### Core

| Variable | Default | Required | Description |
|---|---|---|---|
| `APP_ENV` | `development` | — | `development` / `test` / `production` (or `development-without-ai` to skip AI validation) |
| `APP_NAME` | `sipromo-contextual-promotion` | — | Used in logs, health, telemetry. |
| `API_V1_PREFIX` | `/api/v1` | — | Router prefix (`src/main.py:249`). |
| `LOG_LEVEL` | `INFO` | — | `DEBUG`/`INFO`/`WARNING`/`ERROR`. |

### Database

| Variable | Default | Description |
|---|---|---|
| `DATABASE_URL` | `postgresql+asyncpg://sipromo:sipromo@localhost:5432/sipromo` | SQLAlchemy async URL. Must point to Postgres 16 + `pgvector`. |
| `DATABASE_POOL_SIZE` | `5` | Async pool size. |
| `DATABASE_MAX_OVERFLOW` | `5` | Overflow beyond pool. |

### Auth

| Variable | Default | Required | Description |
|---|---|---|---|
| `JWT_SECRET` | `change-me-in-production` | **yes in prod** | HS256 secret. Startup fails fast if default in non-dev (`src/sipromo/bootstrap/container.py:135`). |
| `JWT_ALGORITHM` | `HS256` | — | `python-jose` algorithm. |
| `ACCESS_TOKEN_EXPIRE_MINUTES` | `30` | — | Token TTL. |
| `AUTH_ENABLED` | `true` | — | `false` → fixed dev actor (`auth_disabled_*`) for `/docs` without token. |
| `AUTH_DISABLED_USER_ID` | `01a00669-…` | — | Dev actor user. |
| `AUTH_DISABLED_UMKM_ID` | `01a00669-…` | — | Dev actor tenant. |
| `AUTH_DISABLED_ROLE` | `owner` | — | Dev actor role. |

### AI — OpenAI only

| Variable | Default | Required | Description |
|---|---|---|---|
| `OPENAI_API_KEY` | `""` | **yes for generation** | `src/sipromo/bootstrap/container.py:76` guards; `validate_ai_configuration()` raises `ConfigurationError` if missing in prod. |
| `OPENAI_MODEL` | `gpt-5-mini` | — | Chat/completions model id. Swap via config — `LLMPort` adapter is provider-agnostic. |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | — | Override for compatible gateways. |
| `OPENAI_TEMPERATURE` | `0.3` | — | Generation temperature. |
| `OPENAI_MAX_OUTPUT_TOKENS` | `6000` | — | `max_output_tokens` per turn. |
| `OPENAI_REASONING_EFFORT` | `low` | — | For reasoning models (`minimal`/`low`/`medium`/`high`). |
| `OPENAI_REQUEST_TIMEOUT_SECONDS` | `180` | — | Client timeout (ms = `*1000` in container). |
| `OPENAI_EMBEDDING_MODEL` | `text-embedding-3-small` | — | Must match `EMBEDDING_DIM`. |
| `EMBEDDING_DIM` | `768` | — | `vector(768)` HNSW dimension — locked by migration. Changing it requires a new migration. |
| `OPENAI_IMAGE_MODEL` | `gpt-image-1` | — | For visual poster generation. |
| `OPENAI_IMAGE_SIZE` | `1024x1024` | — | `1024x1024` / `1024x1792` etc. |
| `OPENAI_IMAGE_QUALITY` | `medium` | — | `low`/`medium`/`high`. |
| `OPENAI_IMAGE_TIMEOUT_SECONDS` | `120` | — | Image gen timeout. |
| `AI_MAX_TOOL_ROUNDS` | `6` | — | Max agent loop rounds. |
| `AI_MAX_TOOL_CALLS` | `16` | — | Max total tool calls per request. |
| `LLM_RUN_MAX_TOTAL_TOKENS` | `60000` | — | Overall run token budget (input+output+tool results). |

> `GEMINI_*` vars in the blueprint are historical — the codebase is **OpenAI-only** (`src/sipromo/infrastructure/ai/openai_compatible_adapter.py`). Keep model ids out of code — always env.

### RAG

| Variable | Default | Description |
|---|---|---|
| `RAG_TOP_K_VECTOR` | `12` | Vector candidates before fusion. |
| `RAG_TOP_K_LEXICAL` | `12` | FTS candidates before fusion. |
| `RAG_FINAL_K` | `8` | Final chunks returned (after RRF + diversity + threshold). |
| `RAG_MIN_SCORE` | `0.55` | Minimum RRF score; below → empty set + warning. |
| `RAG_MAX_CONTEXT_TOKENS` | `6000` | Token budget for retrieved context. |

Tuning guidance: see [Evaluation Report](evaluation_report.md).

### Storage

| Variable | Default | Description |
|---|---|---|
| `CLOUDINARY_CLOUD_NAME` | `""` | Required for uploads; if empty, `CloudinaryAdapter` is `None` and `ingest_knowledge` uses `_UnavailableStorage` (`src/sipromo/bootstrap/container.py:209`). |
| `CLOUDINARY_API_KEY` | `""` | — |
| `CLOUDINARY_API_SECRET` | `""` | — |
| `UPLOAD_MAX_BYTES` | `10485760` (10 MiB) | Knowledge docs cap; assets have a tighter `5 MiB` (`src/sipromo/presentation/api/v1/assets.py:22`). |

---

## 3. Validation & startup

* `Container.validate_ai_configuration()` (`src/sipromo/bootstrap/container.py:135`) runs in `lifespan` for non-`test`/`development` envs — missing `OPENAI_API_KEY`/`OPENAI_MODEL` fails fast with `ConfigurationError` (mapped to `503`).
* Health `ready` pings DB; `dependencies` reports `configured`/`not_configured` without leaking secrets (`src/sipromo/presentation/api/v1/health.py:32`).

---

## 4. Examples

### Development (no AI)

```env
APP_ENV=development-without-ai
DATABASE_URL=postgresql+asyncpg://sipromo:sipromo@localhost:5432/sipromo
AUTH_ENABLED=false
```

### Production

```env
APP_ENV=production
DATABASE_URL=postgresql+asyncpg://user:pass@neon-host/db?ssl=require
JWT_SECRET=<64-char-random>
OPENAI_API_KEY=sk-proj-...
OPENAI_MODEL=gpt-5-mini
CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...
```

Generate a secret:

```bash
python -c "import secrets; print(secrets.token_urlsafe(48))"
```

---

## 5. Changing the model / embedding

1. Set `OPENAI_MODEL` / `OPENAI_EMBEDDING_MODEL` in `.env`.
2. If `EMBEDDING_DIM` changes, create a new Alembic migration that recreates `knowledge_chunks.embedding vector(DIM)` and the HNSW index — never alter in place on production without a branch.

---

## 6. Reference

* Code: `src/sipromo/bootstrap/settings.py:1`, `src/sipromo/bootstrap/container.py:1`
* API docs: `GET /api/v1/health/dependencies` (owner) for live config state.
* Blueprint §22: original env table (Indonesian) — kept as appendix.
