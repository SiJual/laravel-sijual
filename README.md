# SiJual — Modern Nusantara Commerce

> **SiJual** adalah platform *Assistive Intelligence* berbasis web sebagai *MSME Command Center* untuk memberdayakan UMKM Indonesia melalui pencatatan keuangan, riset pasar geodemografis, dan generasi konten pemasaran berbasis AI.

---

## 📌 Deskripsi Proyek

SiJual mengintegrasikan 4 modul utama dalam 1 pusat komando bisnis:

* 🟢 **SiKas** — Pencatatan keuangan pintar berbasis suara (*Speech-to-Text*), kategorisasi otomatis via AI, laporan keuangan real-time, dan rekonsiliasi pembayaran digital QRIS.
* 🔵 **SiPasar** — Riset pasar geodemografis interaktif berbasis lokasi (*Mapbox*), *scraping* data kompetitor, serta indikator *Market Fit Score* (0-100) menggunakan data BPS & OSM.
* 🟠 **SiPromo** — Generator konten pemasaran cerdas (teks + gambar via Flux Schnell & Gemini AI) lengkap dengan galeri templat dan opsi publikasi otomatis.
* 🟤 **SiStok** — Manajemen katalog produk, pemantauan stok, dan sistem peringatan batas minimum inventori.

---

## 🔄 Flow Pengerjaan Proyek

Pengembangan proyek ini mengikuti standar dan dokumentasi internal berikut:

1. **`summary.md`** — Acuan *Ringkasan Eksekutif*, *Problem Statement*, dan *Design System / Tokens* (ekstraksi dari Figma).
2. **`procedure.md`** — *Playbook* & *Rules of Engagement* pelaksanaan task per fase bagi AI Agent dan pengembang.
3. **`implementation_plan.md`** — Spesifikasi *Arsitektur Teknikal*, *Skema Database Supabase PostgreSQL*, *RLS Policies*, dan *Routing System*.
4. **`tasks.md`** — Tracking *Checklist Task & Milestones* dari Fase 1 (*Setup*) hingga Fase 10 (*Submission*).
5. **`revisions.md`** — Catatan *Changelog* dan penyesuaian kebutuhan teknis selama proses pengerjaan.

---

## 🛠️ Cara Instalasi Lokal Proyek

### Prasyarat
* PHP >= 8.2
* Composer
* Node.js >= 18 & NPM
* Database Supabase (PostgreSQL)

### Langkah Instalasi

1. **Clone Repository**
   ```bash
   git clone https://github.com/SiJual/laravel-sijual.git
   cd laravel-sijual
   ```

2. **Install Dependensi**
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Sesuaikan variabel `DB_*`, `SUPABASE_*`, dan `GEMINI_API_KEY` di file `.env`.*

4. **Jalankan Development Server**
   * Terminal 1 (Vite Asset Bundler):
     ```bash
     npm run dev
     ```
   * Terminal 2 (Laravel Server):
     ```bash
     php artisan serve
     ```

5. **Akses Aplikasi**
   Buka browser dan akses `http://localhost:8000`.
