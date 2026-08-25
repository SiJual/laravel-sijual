# Laporan Pengujian Endpoint AI SiPasar Platform

> **Update setelah perbaikan provider:** masalah OSM `406` pada laporan awal sudah
> diperbaiki. Smoke test ulang untuk titik yang sama menemukan 12 kompetitor nyata
> dalam radius 1 km melalui OpenStreetMap, dan endpoint mengembalikan
> `data_source=openstreetmap`, `provider_status=ok`. Detail di bagian paling bawah.

Tanggal pengujian: 23 Agustus 2026 (Asia/Jakarta)  
Mode: live terhadap konfigurasi `.env` lokal  
Model SiPromo: `gpt-5-mini`  
Lokasi Analytics: Klojen, Malang (`-7.9666, 112.6326`), radius 1 km

## Ringkasan eksekutif

| Area | Status | Kesimpulan |
|---|---|---|
| Startup aplikasi | Lulus | Aplikasi monolith memuat kedua modul. |
| SiPromo liveness | Lulus | `200` dalam 9 ms. |
| Database readiness | Lulus sesaat | `200` dalam 1,32 detik. Koneksi kemudian putus ketika vector search berjalan. |
| Analytics health | Lulus dengan catatan | `200`, tetapi hanya melaporkan fallback OSM aktif, bukan apakah fallback benar-benar reachable. |
| Analytics analysis | Respons `200`, data kompetitor tidak valid | BPS/scoring bekerja, tetapi OSM mengembalikan `406`, lalu sistem diam-diam menganggap kompetitor berjumlah nol. |
| SiPromo endpoint end-to-end | Gagal | `POST /api/v1/promotions/generate` mengembalikan `500` setelah 306,121 detik karena koneksi DB terputus saat vector search. |
| OpenAI Embeddings | Lulus | Provider mengembalikan HTTP `200`. |
| OpenAI structured generation langsung | Lulus | Output schema-valid dalam 13,899 detik; 2.244 token. |
| Auth dan validasi | Lulus | SiPromo tanpa token `401`; radius Analytics tidak valid `422`. |

Kesimpulan utama: model OpenAI dan kontrak structured output berfungsi, tetapi endpoint SiPromo belum siap dipakai end-to-end karena masalah koneksi pada tahap RAG/vector search. Endpoint Analytics merespons sukses, tetapi hasil kompetisinya dapat menyesatkan ketika provider OSM gagal.

## Konfigurasi yang terdeteksi

Nilai secret tidak dibaca atau dicantumkan dalam laporan.

| Dependency | Status |
|---|---|
| `.env` | tersedia |
| PostgreSQL | configured dan health check reachable |
| OpenAI API key | configured dan terbukti reachable |
| Model | `gpt-5-mini` |
| JWT | configured, auth aktif |
| Cloudinary | configured |
| Google Places | tidak dikonfigurasi |
| Google Geocoding | tidak dikonfigurasi |
| Fallback Analytics | OSM Overpass + Nominatim |

## Hasil per endpoint

### 1. `GET /api/v1/health/live`

- Status: `200 OK`
- Latency: `0,009 detik`

```json
{
  "status": "ok"
}
```

Penilaian: liveness bekerja sesuai kontrak.

### 2. `GET /api/v1/health/ready`

- Status: `200 OK`
- Latency: `1,320 detik`

```json
{
  "status": "ok",
  "database": "reachable"
}
```

Penilaian: database dapat menerima query pendek. Hasil ini tidak menjamin koneksi tetap hidup selama proses generation yang panjang.

### 3. `GET /v1/health`

- Status: `200 OK`
- Latency: `0,071 detik`

```json
{
  "status": "ok",
  "version": "1.0.0",
  "environment": "development",
  "dependencies": {
    "google_places": "not_configured (OSM fallback active)",
    "google_geocoding": "not_configured (Nominatim fallback active)"
  }
}
```

Penilaian: konfigurasi dilaporkan dengan benar, tetapi wording `fallback active` terlalu optimistis karena endpoint tidak melakukan reachability check.

### 4. `POST /v1/analysis/run`

Skenario: analisis kedai/kategori kopi di Klojen, Malang.

Request:

```json
{
  "business_profile_id": "12345678-1234-5678-1234-567812345678",
  "latitude": -7.9666,
  "longitude": 112.6326,
  "category": "kuliner_kopi",
  "radius_meters": 1000
}
```

- Status: `200 OK`
- Latency: `11,503 detik`
- Analysis ID: `b51906f3-1b03-4f5f-9ed6-7dfaf6268092`

Output penting:

```json
{
  "competitor": {
    "count": 0,
    "avg_rating": 0.0,
    "competition_level": "rendah",
    "competition_score": 0.0,
    "competition_density_per_km2": 0.0,
    "competitors": []
  },
  "geodemografi": {
    "population_estimate": 36413,
    "population_density_per_km2": 11591.0,
    "economic_indicator": "menengah",
    "dominant_consumer_segment": "pekerja_kantoran",
    "area_name": "Klojen"
  },
  "market_potential": {
    "score": 0.6219,
    "label": "sedang",
    "narrative": "[SEDANG] Potensi pasar sedang (skor: 62%). tingkat kompetisi masih rendah sehingga peluang masuk pasar lebih terbuka. estimasi populasi dalam radius sebesar ~36.413 jiwa. daya beli masyarakat sekitar berada di level menengah. segmen konsumen dominan (pekerja kantoran) sangat sesuai dengan kategori usaha ini. Analisis lebih lanjut disarankan sebelum memutuskan ekspansi. Pertimbangkan diferensiasi produk/layanan."
  }
}
```

Trace provider menunjukkan:

```text
POST https://overpass-api.de/api/interpreter -> 406 Not Acceptable
OSM Overpass returned 406; returning empty results
```

Interpretasi:

- Lookup GeoJSON BPS berhasil dan menemukan Klojen.
- Scoring engine berhasil menghitung skor `0,6219`.
- `competitor.count=0` bukan bukti bahwa tidak ada kompetitor. Nilai itu muncul karena provider gagal.
- Karena kegagalan provider dikonversi menjadi list kosong, faktor kompetisi menjadi `1,0` dan menaikkan skor potensi pasar.
- Narasi “tingkat kompetisi masih rendah” tidak aman ditampilkan kepada pengguna pada kondisi ini.

### 5. `GET /api/v1/health/dependencies`

- Status: `200 OK`
- Latency: `0,869 detik`
- Auth: JWT owner valid

```json
{
  "status": "ok",
  "components": {
    "database": "configured",
    "openai": "configured",
    "cloudinary": "configured"
  }
}
```

Catatan: endpoint ini memeriksa keberadaan konfigurasi, bukan reachability OpenAI/Cloudinary. OpenAI kemudian terbukti reachable lewat request nyata.

### 6. `POST /api/v1/promotions/generate`

Test subject yang dipilih secara read-only dari database:

- Role: `owner`
- Produk: `Keripik Pedas 100g`
- Status: `in_stock`
- Stock level: `120`

Request:

```json
{
  "objective": "conversion",
  "content_type": "social_media",
  "platform": "instagram",
  "product_ids": ["998ab9c7-c359-4460-8921-b7b7c676c6ea"],
  "target_audience": "Pelanggan lokal yang mencari produk UMKM berkualitas",
  "tone": "friendly",
  "language": "id",
  "key_message": "Kenalkan keunggulan produk secara jujur dan menarik",
  "call_to_action": "Hubungi kami untuk informasi dan pemesanan",
  "constraints": [
    "Jangan mengarang harga atau diskon",
    "Gunakan hanya fakta yang tersedia"
  ],
  "include_market_context": true,
  "include_business_performance": false
}
```

- Status: `500 Internal Server Error`
- Latency: `306,121 detik`

```json
{
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "internal server error",
    "request_id": "21d74fa8-66d3-4ba1-9ff4-1c1c5d1e7477"
  }
}
```

Urutan yang berhasil sebelum kegagalan:

1. JWT valid dan membership owner ditemukan.
2. Produk valid, aktif, dan memiliki stok.
3. Generation flow dimulai.
4. OpenAI Embeddings mengembalikan HTTP `200`.
5. Vector search dijalankan pada `knowledge_chunks`.
6. AsyncPG melaporkan `ConnectionDoesNotExistError: connection was closed in the middle of operation`.
7. Upaya menandai generation run sebagai failed ikut gagal dengan `PendingRollbackError` karena transaksi sudah invalid.
8. Client hanya menerima generic `500`.

Penilaian:

- Latency lima menit tidak layak untuk request sinkron.
- Error mapping belum mengubah kegagalan database menjadi respons `503` yang informatif.
- Failure bookkeeping belum memakai transaksi/session baru, sehingga trace kegagalan berisiko tidak tersimpan.
- Area yang perlu diaudit pertama: umur transaksi selama external AI call, timeout vector query, pool/reconnect Neon, rollback sebelum failure update, dan performa/index pgvector.

## Tes model OpenAI terisolasi

Karena endpoint end-to-end gagal sebelum chat generation, adapter SiPromo diuji langsung dengan konteks produk terkontrol dan JSON Schema `PromotionOutput` yang sama.

- Provider: OpenAI
- Model: `gpt-5-mini`
- Status: berhasil dan lolos validasi Pydantic
- Latency: `13,899 detik`
- Prompt tokens: `1.299`
- Completion tokens: `945`
- Total tokens: `2.244`

Output:

```json
{
  "title": "Promosi Instagram - Keripik Pedas 100g",
  "primary_copy": "Halo, pencinta pedas! 🌶️ Ada camilan baru yang bisa jadi teman nonton atau nongkrong: Keripik Pedas 100g. Mau tahu detail atau pesan sekarang? Hubungi kami untuk informasi dan pemesanan.",
  "caption": "Keripik Pedas 100g tersedia untuk kamu yang suka rasa pedas. Langsung hubungi kami untuk info dan pemesanan ya! 😊",
  "hashtags": [
    "#KeripikPedas",
    "#CamilanPedas",
    "#SnackKekinian"
  ],
  "call_to_action": "Hubungi kami untuk informasi dan pemesanan",
  "visual_brief": "Foto close-up kemasan Keripik Pedas 100g di atas meja kayu, piring kecil berisi beberapa keripik di sampingnya, pencahayaan hangat, teks overlay pendek: \"Keripik Pedas 100g\" dan CTA kecil: \"Hubungi untuk pesan\".",
  "target_audience_summary": "Pelanggan lokal yang menyukai camilan pedas dan mencari snack untuk dinikmati saat santai atau berkumpul.",
  "rationale": [
    "Caption singkat dan ramah sesuai format Instagram untuk meningkatkan engagement.",
    "Menyertakan CTA langsung sesuai objective conversion: mengarahkan audience menghubungi untuk pemesanan.",
    "Visual brief menonjolkan produk agar mudah dikenali di feed."
  ],
  "claims": [
    "Keripik Pedas 100g",
    "status in_stock"
  ],
  "evidence": [
    {
      "evidence_id": "eval-product-1",
      "source_kind": "tool_result",
      "source_ref": "eval-product-1",
      "supported_claims": [
        "Keripik Pedas 100g",
        "status in_stock"
      ]
    }
  ],
  "warnings": [
    "Brand knowledge tidak ditemukan di RETRIEVED_CONTEXT; verifikasi nama usaha atau identitas brand sebelum publikasi.",
    "Jangan mencantumkan harga atau diskon karena tidak disediakan dalam data."
  ],
  "requires_human_review": true
}
```

### Penilaian kualitas output model

Yang sudah baik:

- JSON mengikuti schema lengkap.
- Bahasa Indonesia natural dan sesuai gaya Instagram.
- Nama produk sesuai konteks.
- Tidak mengarang harga, diskon, sertifikasi, atau URL.
- Evidence terisi dan mengacu ke context block yang benar.
- Warning muncul ketika brand knowledge tidak tersedia.
- `requires_human_review=true` dipatuhi.

Yang perlu diperbaiki:

- Frasa “camilan baru” tidak didukung oleh konteks; produk belum tentu baru.
- “teman nonton atau nongkrong” dan audience “saat santai atau berkumpul” merupakan inferensi kreatif yang tidak berasal dari data.
- Claim `status in_stock` terlalu teknis dan tidak natural untuk audit bisnis.
- Hashtag `#SnackKekinian` memberi positioning yang tidak memiliki evidence.
- Rationale pertama membahas engagement meskipun objective request adalah conversion.
- Completion 945 token relatif besar untuk output akhir yang pendek; schema/reasoning dapat dioptimalkan.

Catatan biaya: tes direct-generation terpanggil dua kali. Panggilan pertama berhasil, tetapi output gagal tercetak karena encoding terminal Windows; panggilan kedua menghasilkan output yang direkam di atas. Endpoint end-to-end juga melakukan satu request Embeddings yang berhasil sebelum vector search gagal.

## Tes autentikasi dan validasi

### SiPromo tanpa bearer token

`POST /api/v1/promotions/generate` tanpa token:

- Status: `401 Unauthorized`

```json
{
  "detail": {
    "error": {
      "code": "UNAUTHENTICATED",
      "message": "Missing bearer token"
    }
  }
}
```

### Analytics dengan radius tidak valid

Request memakai `radius_meters=750`:

- Status: `422 Unprocessable Entity`

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "invalid request",
    "details": [
      {
        "loc": "body.radius_meters",
        "msg": "Value error, radius_meters harus salah satu dari: [500, 1000, 3000, 5000, 10000]"
      }
    ]
  }
}
```

## Prioritas perbaikan

### P0 — sebelum demo/production

1. Jangan menganggap kegagalan competitor provider sebagai `0 competitor`. Kembalikan status `degraded`, confidence, dan warning; jangan menghasilkan narasi “kompetisi rendah”.
2. Perbaiki request OSM Overpass yang menghasilkan `406`, lalu tambahkan provider contract test.
3. Audit transaction boundary SiPromo. Jangan menahan transaksi database melewati external AI call yang panjang.
4. Tambahkan statement timeout untuk vector search dan observability durasi per tahap.
5. Saat DB error, rollback transaction terlebih dahulu dan simpan failure trace memakai session/transaction baru.
6. Map kegagalan DB/provider ke `503`, bukan generic `500`.

### P1 — kualitas AI

1. Perketat policy terhadap kata “baru”, skenario penggunaan, dan positioning tanpa evidence.
2. Ubah claim internal seperti `status in_stock` menjadi fakta audit yang natural atau jangan masukkan ke copy.
3. Validasi setiap hashtag faktual/positioning terhadap evidence.
4. Tambahkan test objective alignment agar rationale dan CTA konsisten dengan conversion/awareness.
5. Kurangi token output dan ukur latency/cost per generation.

### P2 — operasional

1. Health dependencies perlu membedakan `configured`, `reachable`, dan `degraded`.
2. Analytics response perlu field `provider_status`, `data_freshness`, dan `confidence`.
3. Jalankan generation sebagai job async jika target latency realistis melebihi batas HTTP frontend.

## Reproduksi

Script evaluasi yang digunakan:

```bash
uv run python scripts/evaluate_ai_endpoints.py
uv run python scripts/evaluate_sipromo_model.py
```

Script pertama membuat request endpoint nyata dan dapat menyimpan generation trace/draft apabila flow berhasil. Script kedua menguji model secara langsung tanpa RAG/database untuk mengisolasi kualitas provider dan structured output.

## Verifikasi setelah perbaikan pencarian kompetitor

Provider dimigrasikan ke Google Places API (New), dengan fallback OSM memakai
request GET berparameter `data`, User-Agent aplikasi, dan endpoint sekunder.
Semua hasil dideduplikasi dan dihitung ulang jaraknya menggunakan Haversine.

Smoke test ulang `POST /v1/analysis/run`:

- Status: `200 OK`
- Jumlah kompetitor: `12`
- Sumber: `openstreetmap`
- Provider status: `ok`
- Semua hasil: berada dalam radius `1.000 meter`
- Kompetitor terdekat: `Teman Nongkrong Malang`, jarak `163,69 meter`
- Market potential setelah memakai data kompetitor: `0,6088` (`sedang`)

Kegagalan seluruh provider sekarang menghasilkan
`503 COMPETITOR_PROVIDER_UNAVAILABLE`, bukan data kompetitor kosong yang tampak valid.
