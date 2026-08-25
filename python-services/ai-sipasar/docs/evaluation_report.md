# RAG Evaluation Report

> Baseline for hybrid retrieval (pgvector HNSW + FTS + RRF) — reproducible, tenant-isolated, grounded.

Related: `scripts/run_rag_evaluation.py` · [Architecture — Hybrid RAG](architecture.md#4-hybrid-rag) · [Getting Started — seed](getting-started.md#62-seed-knowledge)

---

## 1. Method

Follows blueprint §12, §20.5 and Fase 5 DoD: **Recall@K** and **source precision** recorded in a reproducible report; **tenant leakage must be `0`**.

Pipeline under test is the **production** path — the same `HybridRetriever` used in generation:

```
embed_query → vector top-K + FTS top-K → RRF(K=60) → metadata priority
  → min_score threshold → diversity(≤3 / doc) → final K → token budget
```

No test-only pipeline.

---

## 2. Reproducing

```bash
python -m scripts.run_rag_evaluation \
  --umkm-id <uuid> \
  --eval-set eval_cases.jsonl \
  --out docs/evaluation_report.md
```

### Eval set format — `eval_cases.jsonl` (one JSON per line)

```json
{"case_id":"case-001","query":"promo diskon untuk produk keripik","expected_chunk_ids":["<chunk-uuid>"],"forbidden_claims":["diskon 50%"],"expected_behavior":"generate"}
```

Minimal fields (legacy support):

```json
{"query":"promo diskon untuk produk keripik","expected_chunk_ids":["<chunk-uuid>"]}
```

Richer cases (recommended, ≥50 cases, synthetic UMKM data):

```json
{
  "case_id": "case-001",
  "brief": {"objective":"conversion","content_type":"social_media","tone":"friendly","key_message":"…"},
  "available_facts": [{"kind":"tool_result","ref":"products/<id>"}],
  "expected_sources": ["<chunk-uuid>"],
  "forbidden_claims": ["diskon 50%", "terbaik se-Indonesia"],
  "expected_behavior": "generate|ask_user|warn|reject"
}
```

---

## 3. Metrics

| Metric | Definition | Target |
|---|---|---|
| **Recall@K** | `|retrieved ∩ expected| / |expected|` per case → macro avg | report (higher is better; track per document_type) |
| **Source precision** | `|retrieved ∩ expected| / |retrieved|` | report |
| **Tenant leakage** | `count(retrieved where chunk.umkm_id != target_umkm_id)` | **must be `0`** (also asserted by integration suite under non-superuser role) |
| **Grounded claim ratio** | claims with valid `evidence_id` / total claims | track in `generation_runs.validation_metadata` |
| **Forbidden claim rate** | cases where `forbidden_claims` appear grounded | `0` |

Additional blueprint metrics (§20.5): tool selection accuracy, argument validity, structured output validity, human rubric (relevance, brand fit, clarity, actionability).

> Do not rely solely on LLM-as-judge — include deterministic checks and human review.

---

## 4. Retrieval parameters (defaults)

| Param | Default | Env |
|---|---|---|
| `RAG_TOP_K_VECTOR` | 12 | `RAG_TOP_K_VECTOR` |
| `RAG_TOP_K_LEXICAL` | 12 | `RAG_TOP_K_LEXICAL` |
| `RAG_FINAL_K` | 8 | `RAG_FINAL_K` |
| `RAG_MIN_SCORE` | 0.55 | `RAG_MIN_SCORE` |
| `RAG_MAX_CONTEXT_TOKENS` | 6000 | `RAG_MAX_CONTEXT_TOKENS` |
| RRF K | 60 | code (`hybrid_retriever.py`) |
| Diversity | ≤3 / doc | code |

Changing these requires re-running this report.

---

## 5. Baseline results

> **Placeholder** — run the script against the seeded production tenant and paste the output below. Keep this file in git for reproducibility.

| Metric | Value |
|---|---|
| Macro Recall@K | _n/a_ (run `scripts/run_rag_evaluation.py`) |
| Macro Source Precision | _n/a_ |
| Tenant Leakage | _n/a_ — **must be `0`** |
| K | 5 (or `RAG_FINAL_K` used) |
| Cases | _n/a_ |
| Embedding model | `text-embedding-3-small` (`EMBEDDING_DIM=768`) |
| Date | _n/a_ |
| Commit | _n/a_ (`git rev-parse --short HEAD`) |

Example after running:

```
Cases: 54 | K=8 | Recall@K macro=0.73 | Precision macro=0.61 | Leakage=0
Per-type Recall — brand_guide:0.84  campaign_example:0.78  policy:0.65  faq:0.71  catalog:0.58
```

### How to record

1. Seed the tenant: `python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed`
2. Run eval: `python -m scripts.run_rag_evaluation --umkm-id <uuid> --eval-set eval_cases.jsonl --out docs/evaluation_report.md`
3. Commit the updated table + per-case breakdown (append after this section, do not delete method).

---

## 6. Notes

* Ground truth = chunk ids returned by the tenant's seeded knowledge base (tenant-isolated).
* The eval script uses the same `HybridRetriever` path as generation, so numbers measure **production behavior**.
* For lexical-sensitive queries (SKU, brand term), expect FTS to dominate; for semantic queries, vector dominates — RRF balances both.
* If `RAG_MIN_SCORE` is too high, `retrieval_empty_rate` rises and generation falls back to brief+tools with a warning — monitor that metric.

---

## 7. References

* Code: `src/sipromo/infrastructure/rag/hybrid_retriever.py`, `src/sipromo/infrastructure/rag/chunker.py`, `scripts/run_rag_evaluation.py`
* Config: `src/sipromo/bootstrap/settings.py:57`, [Configuration](configuration.md#2-variables)
* Architecture: [Architecture — Hybrid RAG](architecture.md#4-hybrid-rag)
