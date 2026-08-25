# SiJual — Modern Nusantara Commerce

> **SiJual** adalah platform *Assistive Intelligence* berbasis web sebagai *MSME Command Center* untuk memberdayakan UMKM Indonesia melalui pencatatan keuangan, riset pasar geodemografis, dan generasi konten pemasaran berbasis AI.

---

## 📌 Deskripsi Proyek

SiJual mengintegrasikan 4 modul utama dalam 1 pusat komando bisnis:

* 🟢 **SiKas** — Pencatatan keuangan pintar berbasis suara (*Speech-to-Text* OpenAI Whisper), kategorisasi otomatis via AI, laporan keuangan, dan rekonsiliasi pembayaran digital QRIS.
* 🔵 **SiPasar** — Riset pasar geodemografis interaktif berbasis lokasi (*Mapbox*), analisis kompetitor via Google Places/OSM, serta *Market Potential Score* menggunakan data BPS.
* 🟠 **SiPromo** — Generator konten pemasaran (caption + poster via OpenAI `gpt-image-1`) yang di-*grounding* dengan RAG hibrida dan *tool calling* agar klaim tidak dikarang.
* 🟤 **SiStok** — Manajemen katalog produk, pemantauan stok, dan peringatan batas minimum inventori.

---

## 🧱 Arsitektur

Dua proses aplikasi + satu database, semuanya dijalankan oleh `docker compose`:

```
                    ┌──────────────────────────────┐
  Browser  ────────▶│  app  (Laravel 13, PHP 8.3)  │  :8000
                    │  UI, auth, modul SiKas/SiStok│
                    └───────────┬──────────────────┘
                                │ HTTP (SiPasarBridgeService)
                                ▼
                    ┌──────────────────────────────┐
                    │  ai-sipasar (FastAPI, Py3.13)│  :8010
                    │  SiPasar Analytics + SiPromo │
                    │  RAG, tool calling, scoring  │
                    └───────────┬──────────────────┘
                                │ SQLAlchemy / asyncpg
                                ▼
                    ┌──────────────────────────────┐
                    │  db  (PostgreSQL 16 pgvector)│  :5433
                    └──────────────────────────────┘
```

| Service | Image / build | Port host | Keterangan |
|---|---|---|---|
| `app` | `./Dockerfile` | `8000` | Laravel + Vite build, `php artisan serve` |
| `ai-sipasar` | `./python-services/ai-sipasar/Dockerfile` | `8010` | FastAPI, dokumentasi OpenAPI di `/docs` |
| `db` | `pgvector/pgvector:pg16` | `5433` | user/pass/db = `sijual` |

Urutan boot dijaga otomatis: `db` sehat → `app` menjalankan `php artisan migrate` → `ai-sipasar` menjalankan `alembic upgrade head` (migrasi baseline mendeteksi tabel yang sudah ada dan menjadi *no-op*, lalu menambah tabel `pgvector`).

---

## 🚀 Menjalankan Proyek (Docker Compose)

### Prasyarat
Hanya **Docker Desktop / Docker Engine** dengan plugin Compose v2. PHP, Composer, Node, dan Python **tidak perlu** dipasang di host.

### Langkah

1. **Clone repository**
   ```bash
   git clone https://github.com/SiJual/laravel-sijual.git
   cd laravel-sijual
   ```

2. **Siapkan environment**
   ```bash
   cp .env.example .env
   ```
   Isi kunci API yang ingin dipakai (lihat tabel di bawah). Aplikasi tetap bisa dijalankan tanpa kunci apa pun — fitur AI akan mengembalikan pesan gagal yang eksplisit, dan pencarian lokasi otomatis jatuh ke OSM Nominatim yang gratis.

   > `docker-compose.yml` **tidak** memakai `DB_*` dari `.env`. Database selalu memakai kontainer `db` lokal supaya hasilnya sama di mesin mana pun.

3. **Jalankan seluruh stack**
   ```bash
   docker compose up --build
   ```
   Build pertama memakan waktu beberapa menit (Composer, npm build, dependensi Python).

4. **Akses aplikasi**
   * Aplikasi: <http://localhost:8000>
   * OpenAPI sidecar AI: <http://localhost:8010/docs>
   * Health check sidecar: <http://localhost:8010/api/v1/health/live>

5. **Menghentikan**
   ```bash
   docker compose down          # hentikan
   docker compose down -v       # hentikan + hapus data database
   ```

### Perintah lain yang berguna

```bash
docker compose logs -f app          # log Laravel
docker compose logs -f ai-sipasar   # log FastAPI
docker compose exec app php artisan migrate:status
docker compose exec db psql -U sijual -d sijual
```

---

## 🔑 Variabel Environment

Semua bersifat opsional untuk *boot*; yang kosong hanya menonaktifkan fitur terkait.

| Variabel | Dipakai oleh | Fungsi |
|---|---|---|
| `OPENAI_API_KEY` | app + sidecar | Caption, Copilot, Whisper, generasi poster, embedding RAG |
| `OPENAI_MODEL` | app | Model teks Laravel (default `gpt-4o-mini`) |
| `OPENAI_IMAGE_MODEL` | app + sidecar | Model gambar (default `gpt-image-1`) |
| `SIPROMO_OPENAI_MODEL` | sidecar | Model perencana SiPromo (default `gpt-5-mini`) |
| `MAPBOX_API_KEY` / `MAPBOX_ACCESS_TOKEN` | app | Peta interaktif SiPasar |
| `GOOGLE_PLACES_API_KEY` | app + sidecar | Pencarian lokasi & data kompetitor (fallback: OSM Nominatim) |
| `GOOGLE_GEOCODING_API_KEY` | sidecar | Geocoding (default: mengikuti `GOOGLE_PLACES_API_KEY`) |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | app | Login Google (opsional; login email/password tetap jalan) |
| `APP_KEY` | app | Dibuat otomatis di dalam kontainer bila kosong |
| `JWT_SECRET` | app + sidecar | Token sesi; ada default lokal |
| `CLOUDINARY_*` | sidecar | Penyimpanan aset poster (opsional) |

---

## 💻 Menjalankan Tanpa Docker (opsional)

Jalur ini hanya untuk pengembangan; jalur resmi untuk reproduksi adalah `docker compose`.

**Prasyarat:** PHP >= 8.3, Composer, Node.js >= 20, Python >= 3.12, PostgreSQL 16 + `pgvector`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate

cd python-services/ai-sipasar
python -m venv .venv && .venv/Scripts/pip install -e ".[dev]"   # Linux/macOS: .venv/bin/pip
alembic upgrade head
cd ../..

composer run dev     # menjalankan Laravel, Vite, dan sidecar uvicorn sekaligus
```

---

## 🗂️ Struktur Repositori

```
app/                          Controller, model, dan service Laravel
  Services/Market/            SiPasarBridgeService (klien HTTP ke sidecar)
  Services/AI/                ImageGenerationService, caption, Whisper
python-services/ai-sipasar/   Sidecar FastAPI (SiPasar Analytics + SiPromo)
  src/sipasar/                Analitik pasar: provider, scoring, geodemografi
  src/sipromo/                RAG + tool calling (domain/application/infra)
  migrations/                 Migrasi Alembic (pgvector, trace)
  docs/                       Arsitektur, referensi API, deployment
resources/views/              Blade + Tailwind v4 + Alpine.js
docker/entrypoint.sh          Entrypoint kontainer Laravel
docker-compose.yml            Definisi stack lokal
```

---

## 🔄 Dokumen Acuan Pengembangan

1. **`summary.md`** — *Ringkasan Eksekutif*, *Problem Statement*, dan *Design System / Tokens*.
2. **`procedure.md`** — *Playbook* pelaksanaan task per fase.
3. **`implementation_plan.md`** — Arsitektur teknikal, skema database, routing.
4. **`tasks.md`** — Checklist task & milestone.
5. **`revisions.md`** — Changelog penyesuaian teknis.
6. **`python-services/ai-sipasar/docs/`** — Dokumentasi sidecar AI.

---

## 🩺 Troubleshooting

| Gejala | Solusi |
|---|---|
| `port is already allocated` | Ubah pemetaan port di `docker-compose.yml` (mis. `8001:8000`). |
| `app` tidak sehat, `ai-sipasar` tidak pernah start | `ai-sipasar` menunggu health check `app`. Cek `docker compose logs app`. |
| Fitur AI mengembalikan error | `OPENAI_API_KEY` kosong atau kuota habis. |
| Peta tidak tampil | `MAPBOX_API_KEY` belum diisi. |
| Ingin database bersih | `docker compose down -v && docker compose up --build`. |
