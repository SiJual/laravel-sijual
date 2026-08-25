# SiPasar Analytics Service

**SiPasar** adalah analytics microservice berbasis Python + FastAPI untuk fitur riset pasar & analisis kompetitor UMKM. Service ini menjadi backend dari fitur **SiPasar** — membantu pelaku UMKM membuat keputusan lokasi usaha berbasis data.

## 📐 Arsitektur

```
Frontend (Next.js) ──REST──▶ SiPasar Analytics API (FastAPI + UV)
                                       │
                    ┌──────────────────┴────────────────────────┐
                    ▼                                           ▼
          Google Places API / OSM               BPS Demografi (lokal GeoJSON)
          (data kompetitor)                    (data populasi & ekonomi)
```

## 🚀 Quick Start

### Prerequisites
- Python 3.12+
- [UV](https://docs.astral.sh/uv/) (package & environment manager)

### Setup

```bash
# Clone / masuk ke folder project
cd sipasar-analytics

# Install semua dependencies (UV otomatis buat venv)
uv sync

# Buat file .env dari template
cp .env.example .env
# Edit .env: isi GOOGLE_PLACES_API_KEY jika ada

# Jalankan dev server
uv run uvicorn src.sipasar.main:app --reload
```

### Akses

| URL | Keterangan |
|-----|------------|
| http://localhost:8000 | Root endpoint |
| http://localhost:8000/docs | Swagger UI (interaktif) |
| http://localhost:8000/redoc | ReDoc documentation |
| http://localhost:8000/v1/health | Health check |

---

## 📡 API Endpoints

### `POST /v1/analysis/run`
Jalankan analisis pasar lengkap untuk suatu lokasi.

**Request:**
```json
{
  "business_profile_id": "12345678-1234-5678-1234-567812345678",
  "latitude": -7.9666,
  "longitude": 112.6326,
  "category": "kuliner_kopi",
  "radius_meters": 1000
}
```

**Radius yang diizinkan:** `500`, `1000`, `3000`, `5000`, `10000` (meter)

**Kategori yang tersedia:**
- `kuliner_kopi`, `kuliner_restoran`, `kuliner_warung`, `kuliner_bakery`
- `retail_fashion`, `retail_elektronik`, `retail_sembako`
- `jasa_salon`, `jasa_laundry`, `jasa_bengkel`

**Response:**
```json
{
  "analysis_id": "uuid",
  "competitor": {
    "count": 3,
    "avg_rating": 4.23,
    "competition_level": "sedang",
    "competition_score": 0.42,
    "competitors": [...]
  },
  "geodemografi": {
    "population_estimate": 18500,
    "population_density_per_km2": 8319.0,
    "economic_indicator": "menengah",
    "dominant_consumer_segment": "pelajar_mahasiswa"
  },
  "market_potential": {
    "score": 0.71,
    "label": "tinggi",
    "narrative": "🟢 Potensi pasar tinggi (skor: 71%)..."
  }
}
```

### `GET /v1/analysis/{analysis_id}`
Ambil detail hasil analisis tersimpan.

### `GET /v1/analysis/history?business_profile_id=<uuid>`
Riwayat analisis suatu profil bisnis.

### `POST /v1/analysis/{analysis_id}/rerun`
Analisis ulang dengan parameter baru (opsional: ganti radius).

```json
{ "radius_meters": 3000 }
```

### `GET /v1/health`
Health check service.

---

## 🧠 AI / Analytics Engine

### Modul 1 — Competitor Analysis (PRD §9.1)
```
competition_score = normalize(
    0.50 × competition_density +
    0.30 × avg_rating_kompetitor +
    0.20 × (1 / avg_distance)
)
→ Rendah (< 0.34) | Sedang (0.34–0.67) | Tinggi (> 0.67)
```

### Modul 2 — Geodemographic Analysis (PRD §9.2)
- Spatial join koordinat → kecamatan (geopandas)
- Estimasi populasi via areal interpolation
- Klasifikasi segmen konsumen dari data BPS

### Modul 3 — Market Potential Scoring (PRD §9.3)
```
market_potential_score = normalize(
    0.35 × demand_proxy +
    0.25 × purchasing_power_proxy +
    0.25 × (1 − competition_score) +
    0.15 × category_fit
)
→ Rendah (< 0.34) | Sedang (0.34–0.67) | Tinggi (> 0.67)
```

---

## 🧪 Testing

```bash
# Jalankan semua tests
uv run pytest

# Dengan coverage report
uv run pytest --cov=src/sipasar --cov-report=term-missing

# Test spesifik
uv run pytest tests/test_scoring_service.py -v
```

---

## 📁 Struktur Proyek

```
sipasar-analytics/
├── pyproject.toml              # UV project config + dependencies
├── uv.lock                     # Lockfile deterministik
├── .env.example                # Template environment variables
├── Dockerfile                  # Multi-stage Docker image
├── src/
│   └── sipasar/
│       ├── main.py             # FastAPI app entrypoint
│       ├── api/
│       │   ├── routes_analysis.py  # Endpoint analisis
│       │   └── routes_health.py    # Health check
│       ├── core/
│       │   ├── config.py           # Settings via pydantic-settings
│       │   └── logging.py          # Structured JSON logging
│       ├── services/
│       │   ├── competitor_service.py   # Analisis & scoring kompetitor
│       │   ├── geodemografi_service.py # Analisis demografis
│       │   └── scoring_service.py      # Market potential scoring
│       ├── providers/
│       │   ├── places_provider.py      # Google Places + OSM fallback
│       │   ├── geocoding_provider.py   # Geocoding
│       │   └── bps_provider.py         # Data BPS (GeoJSON lokal)
│       ├── models/
│       │   ├── schemas.py              # Pydantic v2 API schemas
│       │   └── domain.py               # Domain dataclasses
│       └── utils/
│           └── geo_utils.py            # Haversine, bounding box, normalisasi
├── tests/
│   ├── conftest.py
│   ├── test_competitor_service.py
│   ├── test_scoring_service.py
│   └── test_api_analysis.py
└── data/
    ├── bps_kecamatan.geojson   # Sample BPS data (ganti dgn data nyata)
    └── category_mapping.json   # Mapping kategori SiPasar → Places/OSM
```

---

## 🐳 Docker Deployment

```bash
# Build image
docker build -t sipasar-analytics .

# Run container
docker run -p 8000:8000 \
  -e GOOGLE_PLACES_API_KEY=your_key \
  -e APP_ENV=production \
  sipasar-analytics
```

---

## ⚙️ Konfigurasi

Semua konfigurasi dibaca dari environment variables (atau file `.env`). Lihat [`.env.example`](.env.example) untuk lengkapnya.

| Variable | Default | Keterangan |
|----------|---------|------------|
| `GOOGLE_PLACES_API_KEY` | `""` | Google Places API key (opsional; OSM fallback aktif jika kosong) |
| `GOOGLE_GEOCODING_API_KEY` | `""` | Google Geocoding API key |
| `APP_ENV` | `development` | `development` / `staging` / `production` |
| `COMP_W1_DENSITY` | `0.50` | Bobot kepadatan kompetitor dalam scoring |
| `MKT_A1_DEMAND` | `0.35` | Bobot demand proxy dalam market potential |

---

## 📊 Data BPS

File `data/bps_kecamatan.geojson` berisi data demografis contoh. Untuk produksi, download data kecamatan dari [BPS](https://bps.go.id) dan konversi ke format GeoJSON dengan field:

```json
{
  "population": 50000,
  "area_km2": 10.0,
  "population_density_per_km2": 5000,
  "economic_indicator": "menengah",
  "dominant_consumer_segment": "permukiman_umum",
  "purchasing_power_index": 0.55
}
```

---

*Dokumen ini merupakan bagian dari proyek SiJual / AI-SiPasar. Formula scoring bersifat heuristik awal — perlu dikalibrasi dengan data nyata setelah rilis beta.*
