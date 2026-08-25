# Rollback Plan

> Additive migrations + Neon PITR + idempotent re-ingest. Application rollback never drops legacy data.

Related: [Deployment](deployment.md) · [Architecture — ADRs](architecture.md) · `migrations/versions/`

---

## 1. Principles

* **Additive only** — no revision drops or renames legacy columns; old code runs against new schema (forward-compatible).
* **0001 is a no-op guard** — introspects existing Neon tables; on a fresh DB it creates the baseline, on an adopted DB it does nothing. So `downgrade base` never destroys legacy data.
* **New data is disposable** — knowledge/trace/jobs can be re-ingested; PITR restores the rest.

---

## 2. Migration rollback (database)

Alembic downgrade is tested in CI — integration suite runs `downgrade` + `upgrade` round-trip against a fresh `pgvector/pgvector:pg16` container.

| Step | Command | Effect | Data loss |
|---|---|---|---|
| 1 | `alembic downgrade 0002_knowledge_and_trace` | Drops `knowledge_*`, `generation_*`, `content_*`, RLS, HNSW/FTS indexes | Generation/trace data lost — acceptable for rollback. Re-ingestable. |
| 2 | `alembic downgrade 0001_legacy_baseline` | Removes `umkm_memberships`, `idempotency_keys`, `sipromo_jobs` additions | Memberships/jobs lost; re-backfill from `umkm_profiles.user_id` is idempotent. |
| 3 | `alembic downgrade base` | No-op guard: legacy Neon schema left untouched (0001 introspects) | None — legacy tables never dropped by any revision. |

For adoptions where `0001` no-ops, rolling back to `base` only removes what `0002`/`0003` created.

Verify after rollback:

```bash
alembic current
alembic check   # must report "no drift" or clean downgrade
psql -c "\d knowledge_documents"  # should fail if downgraded correctly
```

---

## 3. Application rollback

1. Redeploy the previous image:

   ```bash
   git revert <release-commit>   # or git tag previous
   docker build -t sipromo:prev .
   # push + deploy via your orchestrator (compose / k8s / fly)
   ```
2. New schema is forward-compatible — `0002`/`0003` additions are ignored by old code (no dropped/reused columns; no destructive ALTERs).
3. Pending `sipromo_jobs` rows remain claimable by the old worker (same schema).
4. `requires_human_review` defaults to `true` — a broken generation pipeline never reaches a public channel by itself.

**Cutover without deploy:** generation, approval and publish are separate routes — disable `publish` at the gateway or remove the route to stop external writes while read/search stays available.

---

## 4. Data restore

| Asset | Restore |
|---|---|
| **Postgres** | Neon point-in-time restore (PITR) — restore to branch timestamp before incident, then `alembic upgrade head`. |
| **Cloudinary** | Assets referenced by `cloudinary_public_id` are immutable objects; restore by re-uploading from source files. Checksums allow idempotent re-ingest via `python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed`. |
| **Embeddings** | Recomputed on re-ingest (`EMBEDDING_DIM=768` must match migration). |

---

## 5. Runbook — observed incident

1. **Freeze deploys** — keep serving (read-only tools still safe; generation can be disabled at gateway).
2. **Assess impact:**
   * Only generation quality → disable `POST /promotions/generate`, keep search/list.
   * Auth/tenant → block writes, audit `generation_runs` for cross-tenant access (should be `0`).
   * Data corruption suspected → proceed to step 3.
3. **Database recovery:**
   ```bash
   alembic downgrade 0002_knowledge_and_trace   # or to previous tag
   # Neon PITR restore to <incident - 5 min>
   alembic upgrade head
   alembic check
   ```
4. **Re-ingest knowledge:**
   ```bash
   python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed
   # idempotent — dedup by (umkm_id, checksum_sha256)
   ```
5. **Verify:**
   ```bash
   curl http://localhost:8000/api/v1/health/ready
   curl -H "Authorization: Bearer $OWNER_JWT" http://localhost:8000/api/v1/health/dependencies
   python -m scripts.run_rag_evaluation --umkm-id <uuid> --eval-set eval_cases.jsonl
   # tenant leakage must be 0
   ```
6. **Post-mortem:** record in `docs/evaluation_report.md` + ADR if architectural.

---

## 6. RTO / RPO

| Scenario | RTO | RPO |
|---|---|---|
| App bug (no DB change) | < 5 min (redeploy prev image) | 0 |
| Bad migration `0002`/`0003` | < 15 min (downgrade + redeploy) | knowledge/trace since migration (re-ingestable) |
| Full DB corruption | < 30 min (Neon PITR + `upgrade head` + re-ingest) | ≤ PITR granularity (typically 1–5 min) |

---

## 7. References

* Migrations: `migrations/versions/`, `alembic.ini`, `src/sipromo/infrastructure/db/models/new.py`
* Health: `src/sipromo/presentation/api/v1/health.py`
* RAG eval: `scripts/run_rag_evaluation.py`, [Evaluation Report](evaluation_report.md)
* Security: [Threat Model](threat_model.md) (T1, T6)
