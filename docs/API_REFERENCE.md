# SiJual — API Reference

## Authentication
Seluruh endpoint yang membutuhkan autentikasi dikirimkan melalui middleware `auth.supabase` dengan session JWT token Supabase.

## Endpoints Summary

### 1. Auth & Profile
- `POST /login` — Otentikasi pengguna dengan email & kata sandi.
- `POST /register` — Pendaftaran akun UMKM baru.
- `POST /onboarding` — Pengisian profil usaha & pembuatan outlet utama.

### 2. SiKas (Financials)
- `GET /sikas` — Dasbor statistik saldo & riwayat 10 transaksi terakhir.
- `GET /sikas/transactions` — List transaksi berpaginasi dengan query filter.
- `POST /sikas/transactions` — Tambah transaksi baru (Manual / Voice / QRIS).
- `DELETE /sikas/transactions/{id}` — Hapus transaksi.
- `POST /sikas/voice-input` — Endpoint AI NLP untuk parsing teks suara ke JSON transaksi.
- `POST /sikas/reports/export` — Download CSV laporan keuangan.

### 3. SiPasar (Market Research)
- `POST /sipasar/analyze` — Menjalankan pipeline riset geodemografis (Scraper + BPS Data + Gemini Scoring).
- `GET /sipasar/results/{id}` — Mengambil laporan hasil riset pasar.
- `GET /sipasar/competitors/{id}` — Response JSON daftar kompetitor terdeteksi.

### 4. SiPromo (AI Content Generation)
- `POST /sipromo/generate` — Generate caption & gambar promosi via Flux Schnell.
- `GET /sipromo/preview/{id}` — Detail pratinjau konten promosi.

### 5. Copilot AI Assistant
- `POST /copilot/ask` — Endpoint interaktif untuk mengajukan pertanyaan strategi bisnis ke Copilot AI.
