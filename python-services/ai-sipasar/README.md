# SiPasar Platform - SiPromo + SiPasar Analytics

> **Satu backend FastAPI untuk membuat promosi kontekstual dan menganalisis potensi pasar UMKM.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Python 3.12+](https://img.shields.io/badge/python-3.12%2B-3776AB?logo=python&logoColor=white)](pyproject.toml)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.115-009688?logo=fastapi)](src/main.py)
[![PostgreSQL 16 + pgvector](https://img.shields.io/badge/PostgreSQL-16%2B%20pgvector-4169E1?logo=postgresql)](migrations/)
[![Ruff](https://img.shields.io/badge/lint-ruff-D7FF64?logo=ruff)](pyproject.toml)
[![mypy](https://img.shields.io/badge/type--check-mypy-2A6DBA)](pyproject.toml)

Blueprint produk (bahasa Indonesia, normatif): [`docs/ainov_rag_tool_calling_blueprint.md`](docs/ainov_rag_tool_calling_blueprint.md) | Dokumentasi lengkap: [`docs/index.md`](docs/index.md)

---

## Daftar Isi

- [Ringkasan](#ringkasan)
- [Arsitektur](#arsitektur)
- [Teknologi](#teknologi)
- [Struktur Proyek](#struktur-proyek)
- [Mulai Cepat](#mulai-cepat)
- [Kategori SiPasar Analytics](#kategori-sipasar-analytics)
- [Konfigurasi](#konfigurasi)
- [Referensi API](#referensi-api)
- [Dokumentasi](#dokumentasi)
- [Script](#script)
- [Quality Gates](#quality-gates)
- [Keamanan](#keamanan)
- [Deployment dan Rollback](#deployment-dan-rollback)
- [Evaluasi](#evaluasi)
- [Lisensi](#lisensi)

---

## Ringkasan

Repository ini adalah monolith modular yang menjalankan dua kapabilitas dalam satu proses, satu dependency manifest, satu Docker image, dan satu dokumentasi OpenAPI:

- **SiPromo** — RAG + tool calling untuk membuat, merevisi, menyetujui, dan memublikasikan konten promosi.
- **SiPasar Analytics** — analisis kompetitor, geodemografi, dan skor potensi pasar berbasis Google Places/OSM serta data BPS.

Keduanya tetap dipisahkan sebagai modul Python (`sipromo` dan `sipasar`) agar batas domain jelas, tetapi tidak lagi memiliki repo, virtualenv, entrypoint, atau deployment terpisah.

Generator promosi UMKM pada umumnya menghasilkan copy yang generik, tidak berdasar, dan tidak konsisten dengan brand, seperti harga atau diskon yang dikarang, klaim stok yang salah, dan superlatif tanpa bukti. SiPromo menyelesaikan masalah ini dengan menggrounding setiap hasil pada dua sumber:

1. **Hybrid RAG** - pengetahuan naratif (brand guide, katalog, FAQ, contoh campaign, kebijakan) disimpan sebagai `knowledge_chunks` dengan `vector(768)` HNSW ditambah FTS Postgres, digabung dengan Reciprocal Rank Fusion (`RRF_K=60`).
2. **Tool calling allowlist** - 7 tool read only untuk fakta transaksional (profil, produk, inventaris, market, kompetitor, sales, pencarian knowledge). Argumen divalidasi dengan Pydantic, `umkm_id` disuntik dari JWT, dan output disanitasi sebelum masuk ke konteks model.

Model OpenAI (`gpt-5-mini` sebagai default, dapat diganti lewat env) hanya berperan sebagai **perencana dan penulis copy**. Server yang memvalidasi klaim, menyimpan `draft + sources + trace + revision`, dan **tidak pernah auto publish**. Publikasi wajib melalui keputusan `approved` yang eksplisit. Lihat [`docs/architecture.md`](docs/architecture.md) dan [`docs/api-reference.md`](docs/api-reference.md).

### Jaminan utama

- **Isolasi tenant** - `umkm_id` dari JWT ditambah filter `WHERE umkm_id` di setiap repository ditambah **RLS FORCE** Postgres pada `knowledge_documents`, `knowledge_chunks`, `generation_runs` (`src/sipromo/presentation/api/dependencies.py:22`, `src/sipromo/bootstrap/container.py:55`). Test integrasi memastikan leakage `0` dengan role non superuser.
- **Grounded** - policy deterministik memblokir mata uang, diskon, tanggal, stok, superlatif, sitasi yang hilang, dan CTA yang tidak kompatibel jika tidak ada bukti. `requires_human_review` selalu `true` (`src/sipromo/application/policies/content_safety.py`).
- **Idempoten** - ingest dideduplikasi dengan `(umkm_id, checksum_sha256)`. API yang mengubah data menghormati header `Idempotency-Key` (`scope = user_id:umkm_id:key + sha256(body)`, TTL 1 jam) (`src/sipromo/presentation/api/dependencies.py:95`).
- **Migrasi aditif** - `0001_legacy_baseline` akan no-op pada skema Neon yang sudah ada, sehingga adopsi tidak merusak data (`migrations/versions/`).

---

## Arsitektur

```mermaid
flowchart TB
  Client -->|JWT + PromotionBrief| API[FastAPI create_app\nsrc/main.py:130]
  API -->|get_actor + RLS| UC[GeneratePromotionUseCase\nsrc/sipromo/application/use_cases/generate_promotion.py]
  UC -->|validasi kepemilikan| BusinessRepo
  UC -->|hybrid retrieval| Retriever[HybridRetriever\nvector + FTS + RRF\nsrc/sipromo/infrastructure/rag/hybrid_retriever.py]
  Retriever --> DB[(Postgres 16 + pgvector)]
  UC -->|tool loop max 5 round / 16 call| LLM[OpenAICompatibleAdapter\nsrc/sipromo/infrastructure/ai/openai_compatible_adapter.py]
  LLM <-->|allowlist| Registry[ToolRegistry\nsrc/sipromo/infrastructure/tools/registry.py]
  Registry -->|disanitasi| DB
  LLM -->|PromotionOutput JSON| Policy[DeterministicContentPolicy]
  Policy -->|draft + sources + trace| DB
  UC -->|requires_human_review=true| Human{{Persetujuan manusia}}
  Human -->|POST /approval| PublishUC[PublishContentUseCase]
  PublishUC -->|FOR UPDATE SKIP LOCKED| Jobs[(sipromo_jobs)]
```

**Clean Architecture dan SOLID** dengan aturan dependensi `presentation -> application -> domain`, `infrastructure -> ports + domain`, `bootstrap -> semua` untuk wiring (`src/sipromo/bootstrap/container.py:47`). Domain tidak mengimpor vendor sama sekali. Peta layer lengkap dan 6 ADR ada di [`docs/architecture.md`](docs/architecture.md).

---

## Teknologi

| Lapisan | Pilihan | Keterangan |
|---|---|---|
| Bahasa | **Python 3.12+** | `pyproject.toml:5` |
| API | **FastAPI 0.115** + Uvicorn | factory `create_app` di `src/main.py:130`, OpenAPI di `/docs` |
| Database | **PostgreSQL 16 + pgvector** (HNSW cosine + `to_tsvector('simple')` FTS) | hybrid RRF, RLS per tenant |
| ORM | **SQLAlchemy 2 (async)** + **Alembic** + `asyncpg` | `src/sipromo/infrastructure/db/` |
| AI | **OpenAI** (`gpt-5-mini`, `text-embedding-3-small` `768d`, `gpt-image-1`) | provider di balik `LLMPort`/`EmbeddingPort`, ganti lewat env |
| Analytics | **GeoPandas + Shapely**, Google Places API (New) / OSM, data BPS | kompetitor dalam radius, geodemografi, dan market scoring |
| Media | **Cloudinary** (opsional, hanya server side) | `src/sipromo/infrastructure/storage/cloudinary_adapter.py` |
| Observability | **structlog** + **OpenTelemetry** | `src/sipromo/infrastructure/observability/telemetry.py` |
| Test | **pytest** + **testcontainers** (`pgvector/pgvector:pg16`) | tidak butuh Postgres lokal untuk test integrasi |
| Kualitas | **ruff** (line 100) + **mypy** | `pyproject.toml:47` |

> Blueprint menyebut Gemini, namun codebase yang diimplementasikan **hanya OpenAI** (id model dapat diganti lewat env `OPENAI_*`). `google-genai` masih ada di `pyproject.toml:24` untuk keperluan evaluasi, tidak dipakai saat runtime.

---

## Struktur Proyek

| Path | Fungsi |
|---|---|
| `src/main.py` | Factory FastAPI `create_app` - metadata OpenAPI, `BearerAuth`, parameter Swagger, lifespan |
| `src/sipromo/domain/` | Entity, value object (`PromotionBrief` di `domain/value_objects/promotion_brief.py:16`), domain service |
| `src/sipromo/application/` | Port (`ports/`), use case (`use_cases/`), DTO (`dto/`), policy |
| `src/sipromo/infrastructure/` | `db/` (model `legacy.py`/`new.py`, repository), `ai/` (adapter OpenAI), `rag/` (chunker/retriever), `tools/` (registry dan read/write tool), `storage/`, `observability/` |
| `src/sipromo/presentation/` | HTTP API `v1/` (`promotions.py:52`, `knowledge.py:43`, `health.py:12`, `assets.py:25`, `approvals.py:22`) plus `dependencies.py:22` dan `exception_handlers.py:32` |
| `src/sipromo/bootstrap/` | `settings.py:6` (Pydantic Settings) dan `container.py:47` (DI manual) |
| `src/sipasar/` | API, model, provider lokasi/BPS, dan service scoring SiPasar Analytics |
| `data/` | GeoJSON BPS dan mapping kategori yang digunakan Analytics |
| `migrations/` | Alembic - `0001` guard legacy no-op ke `0002`/`0003` (knowledge/trace/jobs/membership/RLS) |
| `tests/` | `unit/`, `integration/`, dan `analytics/`; semuanya dijalankan dari root |
| `scripts/` | `ingest_seed_knowledge.py` (seed idempoten), `run_rag_evaluation.py` (Recall@K) |
| `docs/` | Dokumentasi profesional - lihat [Dokumentasi](#dokumentasi) |

---

## Mulai Cepat

### Prasyarat

- Python 3.12+ , PostgreSQL 16 + `pgvector` **atau** Docker (untuk testcontainers) , `OPENAI_API_KEY` untuk generate

### Instalasi

```bash
python -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"
cp .env.example .env   # isi DATABASE_URL, OPENAI_API_KEY, JWT_SECRET
```

### Database

```bash
createdb sipromo              # jika pakai Postgres lokal
alembic upgrade head
alembic check                 # harus lapor "no drift"
```

`0001` akan introspeksi skema Neon yang sudah ada dan no-op, jadi aman untuk adopsi tanpa rebuild. Lihat [`docs/deployment.md`](docs/deployment.md).

### Menjalankan

```bash
uvicorn main:create_app --factory --reload --port 8000
```

| URL | Keterangan |
|---|---|
| `http://localhost:8000/docs` | Swagger UI - klik **Authorize lalu BearerAuth** (`Bearer <JWT>`) |
| `http://localhost:8000/redoc` | ReDoc |
| `http://localhost:8000/openapi.json` | OpenAPI JSON untuk generate SDK |
| `http://localhost:8000/api/v1/health/live` | Liveness - tanpa auth |
| `http://localhost:8000/api/v1/health/ready` | Readiness - cek DB |
| `http://localhost:8000/api/v1/health/dependencies` | Status `openai`/`cloudinary` - hanya `owner` |
| `http://localhost:8000/v1/health` | Status SiPasar Analytics dan provider lokasi |
| `http://localhost:8000/v1/analysis/run` | Menjalankan analisis pasar |

> `AUTH_ENABLED=false` memakai aktor dev tetap (`auth_disabled_*` di `.env`) sehingga `/docs` bisa dicoba tanpa token. Jangan pernah dipakai di production.

### Coba alur

```bash
# 1. Seed knowledge (tipe dari nama subdir atau prefix "type__")
python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed

# 2. Generate (Idempotency-Key mencegah double create)
curl -X POST http://localhost:8000/api/v1/promotions/generate \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "objective":"conversion","content_type":"social_media","platform":"instagram",
    "product_ids":["<product-uuid>"],"tone":"friendly","language":"id",
    "key_message":"Produk lokal praktis untuk hadiah",
    "constraints":["Jangan menyatakan diskon"]
  }'

# 3. Approve lalu publish (akan 409 jika belum approved)
curl -X POST http://localhost:8000/api/v1/promotions/<id>/approval \
  -H "Authorization: Bearer $JWT" -H "Content-Type: application/json" \
  -d '{"decision":"approved"}'
curl -X POST http://localhost:8000/api/v1/promotions/<id>/publish \
  -H "Authorization: Bearer $JWT" -H "Content-Type: application/json" \
  -d '{"platform":"instagram"}'
```

---

## Kategori SiPasar Analytics

Gunakan kode kategori berikut pada field `category` di `POST /v1/analysis/run` atau
argumen `--category` pada script pencarian kompetitor:

| Kode kategori | Jenis usaha | Google Places | OpenStreetMap |
|---|---|---|---|
| `kuliner_kopi` | Kafe dan coffee shop | `cafe`, `coffee_shop` | `amenity=cafe` |
| `kuliner_restoran` | Restoran dan rumah makan | `restaurant` | `amenity=restaurant` |
| `kuliner_warung` | Warung, kedai, dan fast food | `restaurant`, `meal_takeaway`, `fast_food_restaurant` | `amenity=fast_food`, `amenity=restaurant` |
| `kuliner_bakery` | Toko roti dan pastry | `bakery` | `shop=bakery` |
| `retail_fashion` | Toko pakaian dan fashion | `clothing_store` | `shop=clothes` |
| `retail_elektronik` | Elektronik, HP, dan gadget | `electronics_store` | `shop=electronics`, `shop=mobile_phone` |
| `retail_sembako` | Sembako, minimarket, dan supermarket | `grocery_store`, `supermarket`, `convenience_store` | `shop=convenience`, `shop=supermarket` |
| `jasa_salon` | Salon, barbershop, dan pangkas rambut | `beauty_salon`, `hair_care` | `shop=beauty`, `shop=hairdresser` |
| `jasa_laundry` | Laundry dan dry cleaning | `laundry` | `shop=laundry`, `shop=dry_cleaning` |
| `jasa_bengkel` | Bengkel mobil dan motor | `car_repair` | `shop=car_repair`, `shop=motorcycle_repair` |

Contoh pencarian kompetitor kafe dalam radius 1 km dari 4Space Mulyosari, Surabaya
(`-7.2708594, 112.7971965`):

```bash
uv run python scripts/test_competitor_search.py \
  --lat -7.2708594 \
  --lon 112.7971965 \
  --category kuliner_kopi \
  --radius 1000
```

Radius yang diterima endpoint analisis adalah `500`, `1000`, `3000`, `5000`, atau
`10000` meter. Google Places digunakan jika `SIPASAR_GOOGLE_PLACES_API_KEY` tersedia;
jika tidak, sistem otomatis memakai OpenStreetMap. Hasil OSM tidak menyediakan rating
dan jumlah ulasan, serta kelengkapannya bergantung pada data komunitas yang tersedia.

Kategori yang tidak terdaftar saat ini tetap diterima dan menggunakan pencarian
`default` yang luas (`amenity=*` dan `shop=*`). Agar hasil kompetitor tetap relevan,
gunakan salah satu kode kategori pada tabel di atas. Scoring kecocokan pasar khusus
tersedia untuk `kuliner_kopi`, `kuliner_restoran`, `kuliner_warung`, `retail_fashion`,
`retail_sembako`, `jasa_salon`, dan `jasa_laundry`. Kategori lainnya memakai nilai
category-fit default `0.60`.

Mapping provider dapat diubah di [`data/category_mapping.json`](data/category_mapping.json).

---

## Konfigurasi

Semua setting berasal dari satu `.env`. Setting Analytics memakai prefix `SIPASAR_` agar tidak bertabrakan dengan konfigurasi SiPromo. Referensi lengkap ada di [`docs/configuration.md`](docs/configuration.md), [`docs/sipasar-analytics.md`](docs/sipasar-analytics.md), dan `.env.example`.

| Variabel | Default | Wajib | Deskripsi |
|---|---|---|---|
| `DATABASE_URL` | `postgresql+asyncpg://sipromo:sipromo@localhost:5432/sipromo` | - | Postgres 16 + `pgvector` |
| `JWT_SECRET` | `change-me-in-production` | **di prod** | secret HS256 - startup akan gagal jika masih default di non dev |
| `OPENAI_API_KEY` | `""` | **untuk generate** | divalidasi di `container.py:135` |
| `OPENAI_MODEL` | `gpt-5-mini` | - | model chat, dapat diganti |
| `OPENAI_EMBEDDING_MODEL` | `text-embedding-3-small` | - | harus cocok dengan `EMBEDDING_DIM` |
| `EMBEDDING_DIM` | `768` | - | `vector(768)` HNSW - perubahan butuh migrasi baru |
| `AI_MAX_TOOL_ROUNDS` / `AI_MAX_TOOL_CALLS` | `6` / `16` | - | batas loop agent |
| `RAG_TOP_K_VECTOR` / `RAG_TOP_K_LEXICAL` / `RAG_FINAL_K` / `RAG_MIN_SCORE` | `12`/`12`/`8`/`0.55` | - | tuning hybrid retrieval |
| `CLOUDINARY_*` | `""` | opsional | jika kosong, upload akan balas `503` |
| `SIPASAR_GOOGLE_PLACES_API_KEY` | `""` | opsional | jika kosong, pencarian kompetitor memakai OSM |
| `SIPASAR_BPS_GEOJSON_PATH` | `data/bps_kecamatan.geojson` | - | data demografi lokal |

Buat secret production:

```bash
python -c "import secrets; print(secrets.token_urlsafe(48))"
```

---

## Referensi API

Base path `API_V1_PREFIX` (default `/api/v1`). Interaktif: `/docs` (Swagger, `persistAuthorization` dan `filter`), `/redoc`, `/openapi.json` (`src/main.py:151`).

| Tag | Method dan Path | Auth | Deskripsi |
|---|---|---|---|
| `health` | `GET /health/live` | tanpa auth | liveness |
| `health` | `GET /health/ready` | tanpa auth | cek DB dan migrasi |
| `health` | `GET /health/dependencies` | `owner` | status provider, tanpa secret |
| `knowledge` | `POST /knowledge/documents` | member | upload multipart lalu chunk dan embed (`202`) |
| `knowledge` | `GET /knowledge/documents` | member | list dengan filter `?status=&document_type=` |
| `knowledge` | `GET /knowledge/documents/{id}` | member | ambil satu dokumen |
| `knowledge` | `DELETE /knowledge/documents/{id}` | member | `?hard=false` archive / `true` hard delete |
| `promotions` | `POST /promotions/generate` | member | RAG dan tool loop menghasilkan `PromotionDraftDTO` (`201`, `requires_human_review=true`) |
| `promotions` | `GET /promotions/{id}` | member | draft terbaru dan revision |
| `promotions` | `POST /promotions/{id}/revisions` | member | tambah revision immutable baru |
| `promotions` | `POST /promotions/{id}/approval` | `owner`/`staff` | `approved`/`rejected`/`changes_requested` |
| `promotions` | `POST /promotions/{id}/publish` | `owner`/`staff` | buat `sipromo_jobs` (`FOR UPDATE SKIP LOCKED`) |
| `approvals` | `GET /promotions/{id}/approvals` | member | riwayat approval |
| `assets` | `POST /assets/upload?kind=logo|product` | member | upload image Cloudinary (maks 5 MiB) |
| `analysis` | `POST /v1/analysis/run` | publik | analisis kompetitor + geodemografi + market scoring |
| `analysis` | `GET /v1/analysis/{id}` | publik | detail hasil analisis |
| `analysis` | `GET /v1/analysis/history?business_profile_id=...` | publik | riwayat analisis profil bisnis |
| `analysis` | `POST /v1/analysis/{id}/rerun` | publik | jalankan ulang analisis |

Hasil competitor menyertakan sumber data, alamat, link peta, dan jarak aktual.
Semua tempat difilter ulang dengan Haversine agar tidak melewati radius request.
Jika Google Places dan OSM sama-sama gagal, endpoint mengembalikan `503`, bukan
menganggap jumlah kompetitor nol.

Semua error memakai format `{ "error": { "code", "message", "request_id", "details"? } }` ditambah header `X-Request-ID` (`src/sipromo/presentation/api/exception_handlers.py:46`). `POST` yang mengubah data menghormati `Idempotency-Key` (`409 IDEMPOTENCY_REPLAY` jika replay). Detail lengkap di [`docs/api-reference.md`](docs/api-reference.md).

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [`docs/index.md`](docs/index.md) | Landing, quick link, layout repo, peta mermaid, konvensi |
| [`docs/getting-started.md`](docs/getting-started.md) | Setup lengkap, seed, contoh curl, troubleshooting |
| [`docs/architecture.md`](docs/architecture.md) | Diagram C4, peta layer, alur data, hybrid RAG, policy tool, **ADR 001 sampai 006** |
| [`docs/api-reference.md`](docs/api-reference.md) | Konvensi, auth, envelope, idempotency, semua endpoint, OpenAPI |
| [`docs/configuration.md`](docs/configuration.md) | Semua env var, validasi, contoh |
| [`docs/sipasar-analytics.md`](docs/sipasar-analytics.md) | Kontrak dan konfigurasi modul Analytics |
| [`docs/deployment.md`](docs/deployment.md) | Docker, strategi migrasi Neon, health, scaling, checklist |
| [`docs/threat_model.md`](docs/threat_model.md) | STRIDE, T1 sampai T10, residual register, verifikasi |
| [`docs/rollback_plan.md`](docs/rollback_plan.md) | Tabel downgrade Alembic, rollback aplikasi, PITR, runbook, RTO/RPO |
| [`docs/evaluation_report.md`](docs/evaluation_report.md) | Recall@K / precision / leakage, cara repro, parameter |
| [`docs/ainov_rag_tool_calling_blueprint.md`](docs/ainov_rag_tool_calling_blueprint.md) | Blueprint produk asli (bahasa Indonesia) |

---

## Script

```bash
# Seed knowledge base tenant (idempoten berdasarkan checksum)
python -m scripts.ingest_seed_knowledge --umkm-id <uuid> --dir knowledge-seed

# Evaluasi baseline RAG - memakai path HybridRetriever yang sama dengan production
python -m scripts.run_rag_evaluation --umkm-id <uuid> --eval-set eval_cases.jsonl --out docs/evaluation_report.md

# Generate ulang OpenAPI untuk SDK
python -c "from src.main import create_app; import json; print(json.dumps(create_app().openapi(), indent=2))" > openapi.json
```

---

## Quality Gates

```bash
ruff check src/ migrations/ tests/ scripts/
ruff format --check src/ migrations/ tests/ scripts/
mypy src/sipromo --ignore-missing-imports
python -m pytest tests/unit tests/integration -q
```

- `ruff` line 100, `target-version py312`, per file ignores di `pyproject.toml:59`
- `mypy` dengan plugin `pydantic.mypy`, `strict false`
- Test integrasi memakai `pgvector/pgvector:pg16` lewat testcontainers, jadi tidak butuh Postgres lokal
- `alembic check` di CI, harus zero drift

---

## Keamanan

Ancaman T1 sampai T10, STRIDE, dan mitigasi lengkap ada di [`docs/threat_model.md`](docs/threat_model.md). Poin penting:

- **T1 IDOR** - dua lapis pertahanan (filter `umkm_id` di repository ditambah RLS FORCE), test integrasi non superuser.
- **T2 prompt injection** - registry allowlist (tanpa `eval`), sanitasi hasil tool (`PRIVATE_FIELD_NAMES`), delimiter prompt, `create_publish_job` tidak bisa dipanggil model, policy deterministik plus review manusia.
- **T3 exfiltrasi** - field privat dibuang, tidak ada `password`/`token` di prompt, `obfuscate()` di log.
- **T8 secret** - hanya lewat env, `.env` di gitignore, startup fail fast jika `JWT_SECRET` masih default di prod.
- Lihat juga `src/sipromo/application/policies/content_safety.py` dan `src/sipromo/infrastructure/tools/read_tools.py`.

---

## Deployment dan Rollback

`Dockerfile` multi stage non root (`python:3.12-slim`, satu proses). Strategi: **Neon branch lalu staging migrate lalu `alembic check` lalu migrate aditif di prod lalu deploy aplikasi lalu feature flag cutover**. Prosedur lengkap, endpoint health, scaling (`FOR UPDATE SKIP LOCKED`), dan checklist ada di [`docs/deployment.md`](docs/deployment.md). Tabel downgrade, PITR, dan runbook ada di [`docs/rollback_plan.md`](docs/rollback_plan.md) (`RTO <5 menit` untuk rollback hanya aplikasi).

---

## Evaluasi

Metode: path production `HybridRetriever` dengan urutan `embed lalu vector top-K + FTS top-K lalu RRF lalu prioritas metadata lalu threshold lalu diversity lalu token budget`. Metrik: **Recall@K**, **source precision**, **tenant leakage = 0** (wajib), ditambah grounded claim ratio dan forbidden claim rate. Cara repro: `scripts/run_rag_evaluation.py` hasilnya di [`docs/evaluation_report.md`](docs/evaluation_report.md).

---

## Lisensi

[MIT](LICENSE) - Copyright (c) 2026 AIC-Hackathon
