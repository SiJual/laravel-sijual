# SiJual — Ringkasan Eksekutif Proyek

> **SiJual** adalah platform *Assistive Intelligence* berbasis web untuk UMKM Indonesia.
> Tagline: *Modern Nusantara Commerce — MSME Command Center*

---

## 1. Problem Statement

UMKM Indonesia menghadapi 3 masalah utama yang menghambat pertumbuhan:

| Masalah | Dampak |
|---|---|
| **Pencatatan keuangan manual & tidak akurat** | Arus kas tidak terpantau, keputusan bisnis berbasis tebakan |
| **Kurangnya akses data pasar & kompetitor** | Tidak bisa membaca peluang & trend lokal secara real-time |
| **Pembuatan konten pemasaran yang lambat & mahal** | Kehilangan momentum penjualan, desain promosi tidak profesional |

## 2. Solusi: 3 Modul Inti + 1 Pusat Komando

```
┌─────────────────────────────────────────────────────────┐
│                    SiJual Hub (Dashboard)                │
│  Command Center — Overview seluruh bisnis UMKM          │
├──────────────┬──────────────┬──────────────┬─────────────┤
│   SiKas      │   SiPasar    │   SiPromo    │   SiStok    │
│   Keuangan   │   Riset      │   Pemasaran  │   Inventori │
│   Pintar     │   Pasar      │   Cerdas     │   Produk    │
└──────────────┴──────────────┴──────────────┴─────────────┘
```

### 🟢 SiKas — Smart Business Management
- Input transaksi via **suara** (Speech-to-Text) atau manual
- Kategorisasi otomatis (AI) untuk income/expense
- Laporan keuangan real-time (daily, weekly, monthly)
- Sinkronisasi **QRIS** untuk rekonsiliasi pembayaran digital
- Export PDF/CSV laporan keuangan
- Multi-outlet support dengan outlet selector

### 🔵 SiPasar — Riset Pasar Geodemografis
- Analisis kompetitor berdasarkan **radius lokasi** (Mapbox)
- Scraping data kompetitor (Playwright + Firecrawl)
- Integrasi **BPS Open Data** & OSM untuk demografi
- **Market Fit Score** (0-100) berbasis AI
- Trend detection & sentiment tagging
- Insight lokal yang mudah dipahami UMKM

### 🟠 SiPromo — Pemasaran & Desain Cerdas
- **Generative AI content** (teks + gambar) menggunakan Flux Schnell
- Template gallery untuk Social Media, Ad Copy, Blog Post, Email
- Caption & hashtag generator otomatis
- Brand consistency guard
- Publish langsung ke Instagram/Facebook via **Meta OAuth**
- Content history & version management

### 🟤 SiStok — Manajemen Inventori (Bonus)
- Katalog produk dengan SKU
- Monitoring stock level & alerts
- Estimated inventory value
- Filter by category (Textiles, Handicrafts, Food & Bev)

## 3. Target Pengguna

| Persona | Deskripsi |
|---|---|
| **Pelaku UMKM** | Pemilik warung, toko batik, kedai kopi, UMKM kuliner |
| **Staf/Kasir** | Operator harian yang mencatat transaksi |
| **Manajer Multi-Outlet** | Pengelola beberapa cabang usaha |

## 4. Value Proposition

| Keunggulan | Detail |
|---|---|
| 🎙️ **Voice-First Transaction** | Input transaksi lewat suara dalam Bahasa Indonesia → parsing noise-robust |
| 🗺️ **Geodemographic Intelligence** | Riset pasar otomatis berbasis lokasi + data BPS |
| 🎨 **AI-Powered Marketing** | Buat konten promosi profesional dalam hitungan detik |
| 🔗 **QRIS Sync** | Rekonsiliasi pembayaran digital otomatis |
| 🤖 **Copilot Bar** | AI assistant cross-module untuk membantu keputusan bisnis |

## 5. Success Metrics (Target)

| Metric | Target |
|---|---|
| TAM Score | ≥ 6.9 / 7 |
| Reduksi waktu pencatatan | ≥ 40% |
| Akurasi kategorisasi transaksi | ≥ 90% |
| Weekly Active Users (awal) | ≥ 500 |

## 6. Diferensiasi dari Kompetitor

1. **Multi-outlet + QRIS Sync** — Tidak hanya pencatatan, tapi rekonsiliasi real pembayaran digital
2. **Hybrid Scraper + BPS Open Data** — Riset pasar dengan data nyata Indonesia, bukan generik
3. **Flux Schnell Self-hosted + Copilot Bar** — Generasi gambar lokal tanpa API mahal + AI assistant terintegrasi

---

## 7. Design Tokens & Theme (Figma Extraction)

### Color Palette

| Token | Hex | Usage |
|---|---|---|
| `--color-primary` | `#9D3D2B` | Brand utama, CTA buttons, active states, accent |
| `--color-primary-light` | `rgba(157,61,43,0.1)` | Icon backgrounds, hover states |
| `--color-primary-subtle` | `rgba(157,61,43,0.05)` | Active sidebar item background |
| `--color-surface` | `#FEF8F6` | Card backgrounds, sidebar, page bg |
| `--color-surface-alt` | `#F8F2F0` | Secondary surface, input backgrounds |
| `--color-surface-warm` | `#FCF8F4` | Alternative warm surface |
| `--color-background` | `#F4F1EA` | Main canvas background (content area) |
| `--color-on-surface` | `#1D1B1A` | Primary text color (headings, labels) |
| `--color-on-surface-variant` | `#56423E` | Secondary text (body, descriptions) |
| `--color-border` | `rgba(137,114,109,0.2)` | Card borders, dividers |
| `--color-border-input` | `#DDC0BA` | Form input borders |
| `--color-success` | `#506356` | Positive values, income, health badges |
| `--color-success-bg` | `rgba(80,99,86,0.1)` | Success badge background |
| `--color-success-surface` | `#D2E8D8` | Income icon circle background |
| `--color-error` | `#BA1A1A` | Error, notification dot |
| `--color-muted` | `#E7E1DF` | Disabled, muted backgrounds, tags |
| `--color-white` | `#FFFFFF` | White overlays, form inputs |
| `--color-hero-accent` | `#FFDAD3` | Decorative blurs, hero section accent |

### Gradients

| Token | Value | Usage |
|---|---|---|
| `--gradient-hero` | `linear-gradient(135deg, rgba(157,61,43,0.8), rgba(188,85,64,0.9))` | Right panel login hero |
| `--gradient-ai-border` | `linear-gradient(148deg, #9D3D2B 0%, transparent 50%, #506356 100%)` | AI-powered card border glow |

### Typography

| Token | Font Family | Weight | Size | Line Height | Usage |
|---|---|---|---|---|---|
| `--font-display` | **Noto Serif** | Bold (700) | 48px / 32px / 24px | 60px / 40px / 32px | Hero headings, section titles |
| `--font-body` | **Plus Jakarta Sans** | Regular (400) | 16px / 18px | 24px / 28px | Body text, descriptions |
| `--font-body-semibold` | **Plus Jakarta Sans** | SemiBold (600) | 14px / 12px | 20px / 16px | Labels, nav items, badges |
| `--font-brand` | **Liberation Serif** | Bold (700) | 24px | 32px | "SiJual" logo wordmark (login) |

### Spacing Scale

| Token | Value | Usage |
|---|---|---|
| `--space-xs` | 4px | Inner gaps, checkbox spacing |
| `--space-sm` | 8px | Nav gaps, icon margins |
| `--space-md` | 16px | List padding, card inner spacing |
| `--space-lg` | 24px | Section gaps, card padding |
| `--space-xl` | 32px | Welcome section padding, major gaps |
| `--space-2xl` | 40px | Page padding, main content margins |
| `--space-3xl` | 48px | Hero illustration padding |

### Border Radius

| Token | Value | Usage |
|---|---|---|
| `--radius-none` | 0px | Login form inputs (sharp) |
| `--radius-sm` | 4px | Tags, badges, checkboxes |
| `--radius-md` | 8px | Buttons, nav items, filter chips |
| `--radius-lg` | 12px | Cards, panels, content sections |
| `--radius-full` | 9999px | Avatar circles, pills, search bar |

### Shadow / Elevation

| Token | Value | Usage |
|---|---|---|
| `--shadow-sm` | `0 1px 1px rgba(0,0,0,0.05)` | Subtle lift (buttons, sidebar) |
| `--shadow-md` | `0 4px 10px rgba(45,43,42,0.05)` | Card hover, stat cards |
| `--shadow-lg` | `0 4px 20px rgba(45,43,42,0.05)` | Elevated cards, main panels |
| `--shadow-hero` | `0 8px 32px rgba(0,0,0,0.1)` | Glassmorphism card (login) |

### Glassmorphism

| Property | Value | Usage |
|---|---|---|
| Background | `rgba(255,255,255,0.1)` | Login hero card |
| Backdrop Blur | `6px` | Glassmorphism blur effect |
| Border | `1px solid rgba(255,255,255,0.2)` | Glass card border |

### Reusable Component Inventory (dari Figma)

| Component | Variants | Halaman |
|---|---|---|
| **SideNavBar** | Active/inactive items, logo + "New Transaction" CTA | Semua dashboard |
| **TopAppBar** | Search bar, notification bell (with dot), avatar | Semua dashboard |
| **StatCard** | SiKas (revenue), SiPasar (score bar), SiPromo (campaigns) | Hub Dashboard |
| **TransactionListItem** | Income (+green) / Expense (-dark), selected indicator | SiKas Riwayat |
| **TransactionDetail** | Summary card + AI Insights | SiKas Riwayat |
| **MarketAlertCard** | Demand Spike / Promo Suggestion icon variants | Hub, SiPasar |
| **VoiceInputBar** | Mic button + waveform + text preview | SiKas Dashboard |
| **FilterChip** | Date Range / Type / Category with dropdown icons | Transaction History |
| **BottomNavBar** | Hub / SiKas / SiPasar / SiPromo tabs (mobile) | Mobile views |
| **RevenueChart** | Line chart (Masuk vs Keluar) | SiKas Dashboard |
| **MapView** | Radius selector + competitor pins + density overlay | SiPasar |
| **MarketFitScoreCard** | Score (0-100), radius, competitors, density | SiPasar |
| **ContentBriefForm** | Prompt input + content type selector (Social/Ad/Blog) | SiPromo |
| **ContentPreviewCard** | Image + carousel thumbnails + Share/Download CTA | SiPromo Result |
| **ProductTable** | SKU, Category, Price, Stock Level, Status, Actions | SiStok |
| **CopilotBar** | AI chat interface, cross-module | Global |

---

## 8. Tech Stack (Keputusan Proyek)

| Layer | Teknologi |
|---|---|
| **Framework** | Laravel (latest) |
| **Styling** | Tailwind CSS v4 |
| **Database** | Supabase (PostgreSQL) |
| **Auth** | Supabase Auth (email/password + Google OAuth) |
| **Storage** | Supabase Storage |
| **Security** | Row Level Security (RLS) |
| **AI Services** | Gemini API (primary) + OpenAI (fallback) |
| **Image Gen** | Flux Schnell (self-hosted) |
| **Maps** | Mapbox GL JS |
| **Build Tool** | Vite (Laravel default) |

> **Catatan:** PRD asli menggunakan Next.js + Firebase, tetapi keputusan tim mengadaptasi ke **Laravel + Supabase** sesuai arahan proyek.
