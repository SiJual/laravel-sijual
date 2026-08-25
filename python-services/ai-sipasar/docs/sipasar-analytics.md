# SiPasar Analytics

SiPasar Analytics sekarang merupakan modul di dalam SiPasar Platform, bukan service atau repository terpisah. Modul ini berjalan melalui entrypoint yang sama dengan SiPromo (`main:create_app`) dan muncul di OpenAPI yang sama pada `/docs`.

## Kapabilitas

- Analisis kompetitor dari Google Places API (New) dengan fallback dua endpoint OpenStreetMap Overpass.
- Lookup geodemografi dari GeoJSON BPS lokal.
- Scoring potensi pasar berdasarkan demand, daya beli, tingkat kompetisi, dan kecocokan kategori.
- Penyimpanan sementara riwayat analisis dalam memory. Untuk produksi multi-worker, store ini perlu dipindah ke database bersama.

## Endpoint

| Method | Path | Fungsi |
|---|---|---|
| `GET` | `/v1/health` | Status modul dan provider eksternal |
| `POST` | `/v1/analysis/run` | Menjalankan analisis baru |
| `GET` | `/v1/analysis/{analysis_id}` | Mengambil detail analisis |
| `GET` | `/v1/analysis/history?business_profile_id=...` | Mengambil riwayat profil bisnis |
| `POST` | `/v1/analysis/{analysis_id}/rerun` | Menjalankan ulang analisis |

Path `/v1/...` dipertahankan agar client dari repository Analytics lama tetap kompatibel.

## Konfigurasi

Semua variabel memakai prefix `SIPASAR_` dan diletakkan di `.env` root:

```dotenv
SIPASAR_APP_ENV=development
SIPASAR_GOOGLE_PLACES_API_KEY=
SIPASAR_GOOGLE_GEOCODING_API_KEY=
SIPASAR_BPS_GEOJSON_PATH=data/bps_kecamatan.geojson
SIPASAR_CATEGORY_MAPPING_PATH=data/category_mapping.json
```

API key Google opsional. Tanpa key, provider kompetitor memakai OSM dan geocoding memakai Nominatim.

`SIPASAR_GOOGLE_PLACES_API_KEY` harus berasal dari project Google Cloud yang telah
mengaktifkan **Places API (New)** dan billing. Alias lama `GOOGLE_PLACES_API_KEY`
juga diterima. Pencarian memakai Nearby Search dengan circle restriction, maksimal
20 hasil, lalu server menghitung ulang jarak Haversine dan membuang hasil di luar radius.

Setiap competitor mengandung `place_id`, `address`, `source`, `maps_uri`, dan jarak.
Bagian competitor juga mengandung `data_source` (`google_places` atau
`openstreetmap`) serta `provider_status`. Jika seluruh provider tidak dapat diakses,
endpoint mengembalikan `503 COMPETITOR_PROVIDER_UNAVAILABLE`; kegagalan provider
tidak lagi dilaporkan sebagai nol kompetitor.

Tes pencarian live tanpa menjalankan seluruh pipeline:

```bash
uv run python scripts/test_competitor_search.py \
  --lat -7.9666 --lon 112.6326 \
  --category kuliner_kopi --radius 1000
```

## Menjalankan dan menguji

Dari root repository:

```bash
pip install -e ".[dev]"
uvicorn main:create_app --factory --reload --port 8000
pytest tests/analytics
```

Kode berada di `src/sipasar`, fixture data di `data`, dan test di `tests/analytics`. Dependency serta Docker image dikelola hanya dari root.
