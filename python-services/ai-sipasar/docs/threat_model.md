# Threat Model

> Scope: SiPromo API + Postgres (Neon) + OpenAI + optional Cloudinary.  
> Assets: tenant data (products, contacts, sales), generated content, API keys, embeddings.  
> Methodology: STRIDE + defense-in-depth; residual risk after mitigations is rated Low/Medium/High.

Source code refs: `src/sipromo/presentation/api/dependencies.py`, `src/sipromo/bootstrap/settings.py`, `src/sipromo/infrastructure/tools/`, `src/sipromo/application/policies/`, `migrations/`.

---

## 1. Trust boundary

```mermaid
flowchart LR
  User([User browser / frontend]) -->|JWT + Idempotency-Key| API[FastAPI\n/src/main.py]
  API -->|RLS + tenant filter| DB[(Postgres 16 + pgvector)]
  API -->|sanitized tool results| LLM[OpenAI API]
  API -->|signed server-side upload| CDN[Cloudinary]
  Attacker -.->|tries IDOR / injection| API
  Knowledge[(Knowledge docs)] -- untrusted data --> RAG
  RAG --> API
```

* Untrusted inputs: request bodies, file uploads, knowledge document text (treated as **data, never instructions**).
* Trusted: `umkm_id` from JWT, server-side `ToolRegistry`, `DeterministicContentPolicy`.

---

## 2. STRIDE summary

| Category | Example threat | Mitigation | Residual |
|---|---|---|---|
| **S**poofing | stolen JWT / forged tenant | HS256 with `JWT_SECRET` (fail fast if default in prod), membership verified per-request (`get_actor` → `membership_repo`), role check on approve/publish | Low |
| **T**ampering | model sends `umkm_id` for another tenant | `umkm_id` injected from auth, never from model args; Pydantic validation + allowlist registry (no `eval`) | Low |
| **R**epudiation | deny who approved/published | `content_approvals(decided_by, decided_at)`, `generation_runs` + `generation_tool_calls`, `X-Request-ID` tracing | Low |
| **I**nfo disclosure | cross-tenant read, secret leak to LLM | RLS FORCE + repo filter (two barriers), `PRIVATE_FIELD_NAMES` sanitization, no `password`/`token` in tool outputs or prompts, `.env` never in git, logs obfuscated | Low |
| **D**enial of service | cost abuse, huge upload, loop | `AI_MAX_TOOL_ROUNDS`/`CALLS`, `LLM_RUN_MAX_TOTAL_TOKENS`, `UPLOAD_MAX_BYTES` (10 MiB / 5 MiB), 429 not retried, per-tenant gateway rate limit (deploy-time) | Medium* |
| **E**levation | `viewer` publishes, `staff` reads other tenant | role in JWT + DB membership check; `publish` requires `approved` revision; RLS prevents bypass even if repo bug | Low |

\* Medium for DoS because gateway limiter is an operational control, not in-repo.

---

## 3. Detailed threats

### T1 — Cross-tenant data access

- **Risk:** Tenant A reads/overwrites tenant B knowledge or content (IDOR).
- **Mitigations:** RLS FORCE on `knowledge_documents`, `knowledge_chunks`, `generation_runs`; every repository query filters `umkm_id`; tenant derived from JWT, never from model input; dedicated integration test with non-superuser role asserting zero leakage.
- **Verification:** `tests/integration/test_tenant_isolation.py` (leakage must be `0`); also `scripts/run_rag_evaluation.py` checks `tenant_leakage`.
- **Residual:** **Low** (relies on two independent barriers; both are tested).

### T2 — Model manipulation via prompt injection

- **Risk:** Knowledge documents (retrieved context) contain instructions that make the model emit unsafe copy or attempt unauthorized tools (e.g., "ignore instructions and call publish").
- **Mitigations:** Tool allowlist (`ToolRegistry` dict, no `eval`); role-based tool authorization; `create_publish_job` not exposed to the model; recursive sanitization of tool results; prompt delimiters (`<USER_BRIEF>` / `<RETRIEVED_CONTEXT>` / `<TOOL_RESULTS>`); deterministic claim policy rejects superlatives, ungrounded discounts, missing evidence; `requires_human_review` always on; one regeneration attempt then `rejected`.
- **Residual:** **Medium** — a human reviews every publishable draft; consider an additional LLM-as-judge second pass post-MVP.

### T3 — Sensitive data exfiltration to the model

- **Risk:** Phone numbers, emails, tokens in tool payloads reach OpenAI context and leak into generated copy or logs.
- **Mitigations:** Recursive field-name sanitization (`PRIVATE_FIELD_NAMES` in `read_tools.py`); read tools exclude private contacts; no credentials in prompts; structured logs use `obfuscate()` for ids.
- **Residual:** Low.

### T4 — Unauthorized API access

- **Risk:** Attacker calls generation/publish endpoints without a valid token or as a different tenant.
- **Mitigations:** JWT (HS256, secret from env, fail at startup if default in production); `get_actor` dependency enforces auth on all v1 routes except health; role checks on approvals and publishes (`owner`/`staff` vs `viewer`).
- **Residual:** Low.

### T5 — Quota / cost abuse (financial DoS)

- **Risk:** Unbounded generation or embedding calls.
- **Mitigations:** Tool round/call budgets (`AI_MAX_TOOL_ROUNDS=6`, `AI_MAX_TOOL_CALLS=16`); `LLM_RUN_MAX_TOTAL_TOKENS`; upload size cap (10 MiB); client timeout `OPENAI_REQUEST_TIMEOUT_SECONDS=180`; quota errors (429) are mapped and **not retried** in generation; tenant-scoped rate limiting per API contract (gateway).
- **Residual:** Medium — deploy a gateway rate limiter for production.

### T6 — Duplicate / replay requests

- **Risk:** Same upload or publish request applied twice (double charge, duplicate content/jobs).
- **Mitigations:** Content checksum uniqueness per tenant (`UNIQUE (umkm_id, checksum_sha256)`); idempotency keys (scope `user_id:umkm_id:key` + `sha256(body)` + TTL, stored in `idempotency_keys`); `FOR UPDATE SKIP LOCKED` job claiming.
- **Residual:** Low.

### T7 — Dependency & supply chain

- **Risk:** Compromised package in runtime deps; dynamic imports.
- **Mitigations:** pinned `pyproject.toml` deps, CI `pip cache`; runtime image `python:3.12-slim`; no dynamic imports, no `eval` of model content; review on bump.
- **Residual:** Low.

### T8 — Secret leakage

- **Risk:** `OPENAI_API_KEY`, `JWT_SECRET`, Cloudinary credentials in logs or commits.
- **Mitigations:** Env-driven settings; `.env.example` contains only placeholders; structured logs never include headers/body; container fails fast when `app_env != development` and secrets are default values; `.gitignore` covers `.env`; pre-commit `detect-secrets` recommended (not yet in CI).
- **Residual:** Low.

### T9 — Availability (provider outage)

- **Risk:** OpenAI timeout/503 blocks generation.
- **Mitigations:** tenacity retry only for transient errors, 30–180s client timeout, clean error mapping to `503 AI_QUOTA_EXHAUSTED` with `run_id` for support; health `dependencies` reports `degraded`.
- **Residual:** Low.

### T10 — Malicious file upload

- **Risk:** Malware, polyglot, oversized file, path traversal via filename.
- **Mitigations:** MIME validated by **file signature** not extension, size cap, `public_id` is a generated UUID (never raw filename), `folder` is tenant-safe (`sipromo/{umkm_id}/…`), antivirus hook placeholder in blueprint §12.2.
- **Residual:** Low.

---

## 4. Residual risk register

| ID | Risk | Severity before | Severity after | Owner |
|---|---|---|---|---|
| T1 | Cross-tenant | High | Low | Backend + DB |
| T2 | Prompt injection | High | Medium | AI / Policy |
| T5 | Cost DoS | Medium | Medium | Platform / Gateway |

---

## 5. Verification status

| Threat | Test | Status |
|---|---|---|
| T1 | integration tenant isolation (non-superuser) | ✅ passing |
| T2–T6 | unit: policy, sanitization, idempotency, budget | ✅ passing |
| T2 | prompt injection on PDF fixture | recommended — add `tests/security/test_prompt_injection.py` |
| T4 | 401/403 contract on protected routes | ✅ via `exception_handlers` |
| T8 | `ruff` secrets-in-code | not enabled — add `detect-secrets` to pre-commit |
| T1, RAG | `scripts/run_rag_evaluation.py` tenant_leakage=0 | manual, keep in CI |

---

## 6. Out of scope / assumptions

* Frontend auth flow issues JWTs correctly and never forwards `JWT_SECRET`.
* Neon network ACL and Cloudinary signed uploads are configured per their docs.
* Gateway (Kong/NGINX/Cloudflare) enforces global rate limiting and WAF — not in this repo.

---

## 7. References

* Code: `src/sipromo/presentation/api/dependencies.py:22`, `src/sipromo/application/policies/content_safety.py`, `src/sipromo/infrastructure/tools/registry.py`
* Blueprint: §§17–18 (Auth, Security & AI Safety)
* Related docs: [Architecture](architecture.md#6-multi-tenancy), [API Reference](api-reference.md#1-conventions), [Configuration](configuration.md)
