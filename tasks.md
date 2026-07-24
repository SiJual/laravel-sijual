# SiJual — Tasks & Milestones Checklist

> **Tim:** Fardan (F) & Nafi (N)
> **Total Tickets PRD:** 70 | **Estimasi SP:** ~375
> **Target:** Production-Ready

---

## Phase 1: Environment & Project Setup 🏗️
> **Sprint 0** | **Target: Minggu 1** | **~21 SP**

### Definition of Done — Phase 1
- [x] Laravel project berjalan di localhost
- [x] Tailwind CSS v4 terintegrasi via Vite dan rendering benar
- [x] Supabase project terbuat dan connected dari Laravel
- [x] Environment variables terkonfigurasi (.env)
- [x] Git repo initialized dengan branching strategy
- [x] Folder structure sesuai implementation plan

### Tasks

- [x] **(F)** Inisialisasi proyek Laravel (latest) via Composer
- [x] **(F)** Setup Vite + Tailwind CSS v4 (`@tailwindcss/vite` plugin)
- [x] **(F)** Konfigurasi `resources/css/app.css` dengan design tokens dari Figma (`@theme` block)
- [x] **(F)** Import Google Fonts: Noto Serif + Plus Jakarta Sans
- [x] **(N)** Buat proyek Supabase baru (PostgreSQL instance)
- [x] **(N)** Konfigurasi `.env` Laravel → Supabase PostgreSQL (Session Pooler)
- [x] **(N)** Konfigurasi `config/supabase.php` (URL, anon key, service key)
- [x] **(F)** Setup folder structure sesuai implementation plan (Controllers, Services, Views, dll.)
- [x] **(N)** Setup Supabase Auth settings (enable email/password + Google OAuth)
- [x] **(N)** Buat `config/ai.php` untuk API keys (Gemini, OpenAI, Flux)
- [x] **(F)** Buat base Blade layout: `layouts/app.blade.php` (authenticated)
- [x] **(F)** Buat base Blade layout: `layouts/guest.blade.php` (login/register)
- [x] **(N)** Inisialisasi Git repo + README + `.gitignore`
- [x] **(N)** Setup AI SDK Wrapper boilerplate: `GeminiService.php` + `OpenAIFallbackService.php`

---

## Phase 2: Database Schema & Authentication 🔐
> **Sprint 1** | **Target: Minggu 2** | **~28 SP**

### Definition of Done — Phase 2
- [x] Semua tabel Supabase PostgreSQL terbuat sesuai ERD
- [x] RLS policies aktif pada semua tabel tenant
- [x] Login / Register / Logout berfungsi via Supabase Auth
- [x] Google OAuth login berfungsi
- [x] Auth middleware Laravel memvalidasi Supabase JWT
- [x] Profil UMKM & Outlet bisa dibuat dan diedit

### Tasks

#### Database (N)
- [x] **(N)** Buat SQL migration: `001_create_users_table.sql`
- [x] **(N)** Buat SQL migration: `002_create_umkm_profiles_table.sql`
- [x] **(N)** Buat SQL migration: `003_create_outlets_table.sql`
- [x] **(N)** Buat SQL migration: `004_create_categories_table.sql`
- [x] **(N)** Buat SQL migration: `005_create_transactions_table.sql`
- [x] **(N)** Buat SQL migration: `006_create_reports_table.sql`
- [x] **(N)** Buat SQL migration: `007_create_market_analyses_table.sql`
- [x] **(N)** Buat SQL migration: `008_create_competitors_table.sql`
- [x] **(N)** Buat SQL migration: `009_create_demographics_table.sql`
- [x] **(N)** Buat SQL migration: `010_create_content_assets_table.sql`
- [x] **(N)** Buat SQL migration: `011_create_publish_jobs_table.sql`
- [x] **(N)** Buat SQL migration: `012_create_invites_table.sql`
- [x] **(N)** Buat SQL migration: `013_create_products_table.sql`
- [x] **(N)** Buat SQL migration: `014_create_indexes.sql` (composite indexes)
- [x] **(N)** Buat SQL migration: `015_create_rls_policies.sql`
- [x] **(N)** Buat SQL migration: `016_seed_default_categories.sql`
- [x] **(N)** Jalankan semua migration di Supabase SQL Editor

#### Authentication (F + N)
- [x] **(N)** Buat `SupabaseAuthService.php` (signIn, signUp, signOut, getUser)
- [x] **(F)** Buat `SupabaseAuth` middleware (validate JWT dari session)
- [x] **(F)** Buat custom Auth Guard untuk Supabase
- [x] **(F)** Konfigurasi `config/auth.php` untuk Supabase guard
- [x] **(N)** Buat `GoogleAuthController.php` (redirect + callback via Supabase)
- [x] **(F)** Buat `LoginController.php` (show + store + destroy)
- [x] **(F)** Buat `RegisterController.php` (show + store)
- [x] **(F)** Buat `ForgotPasswordController.php` (show + store)

#### Auth UI — Blade Views (F)
- [x] **(F)** Buat `auth/login.blade.php` — sesuai Figma 2:2 (desktop split layout)
  - Left panel: Logo, "Selamat Datang di SiJual", form fields, CTA
  - Right panel: Glassmorphism hero card + "Modern Nusantara Commerce"
- [x] **(F)** Buat `auth/register.blade.php` — sesuai Figma 2:269 (mobile)
- [x] **(F)** Buat `auth/forgot-password.blade.php`
- [x] **(F)** Indonesian error messages untuk semua form validation

#### Profile & Onboarding (N)
- [x] **(N)** Buat Eloquent Models: User, UmkmProfile, Outlet
- [x] **(N)** Buat `ProfileController.php` (edit, update, onboarding)
- [x] **(N)** Buat `OutletController.php` (CRUD)
- [x] **(N)** Buat `EnsureProfileComplete` middleware
- [x] **(N)** Buat `CheckRole` middleware (RBAC: owner, staff, viewer)
- [x] **(F)** Buat `onboarding/profile-setup.blade.php` — sesuai Figma 2:416
- [x] **(F)** Buat `profile/edit.blade.php`

#### Routes
- [x] **(F)** Setup `routes/web.php` — Guest routes (login, register, Google OAuth)
- [x] **(N)** Setup `routes/web.php` — Authenticated routes (onboarding, dashboard, profile)

---

## Phase 3: Core UI Shell & Navigation Components 🎨
> **Sprint 2** | **Target: Minggu 3** | **~27 SP**

### Definition of Done — Phase 3
- [x] SideNavBar, TopAppBar, BottomNavBar berfungsi dan responsive
- [x] Dashboard Hub menampilkan data placeholder yang benar
- [x] Navigasi antar modul (Hub → SiKas → SiPasar → SiPromo → SiStok) berfungsi
- [x] Outlet selector di sidebar berfungsi
- [x] Mobile responsive breakpoints benar (375px, 768px, 1280px)

### Tasks

#### Shared Components (F)
- [x] **(F)** Buat `components/navigation/side-nav-bar.blade.php`
  - Logo SiJual + "MSME Command Center"
  - "+ New Transaction" CTA button
  - Nav items: SiJual Hub, SiKas, SiPasar, SiPromo, SiStok
  - Active state: left border indicator (4px #9D3D2B)
  - Bottom: Settings + Support links
- [x] **(F)** Buat `components/navigation/top-app-bar.blade.php`
  - Search bar (rounded-full, bg-surface-alt)
  - Notification bell (with red dot badge)
  - User avatar/icon
- [x] **(F)** Buat `components/navigation/bottom-nav-bar.blade.php`
  - Mobile only: Hub / SiKas / SiPasar / SiPromo tabs
  - Active tab highlight
- [x] **(F)** Buat `components/ui/stat-card.blade.php`
  - Variants: SiKas (Penjualan), SiPasar (Market Score), SiPromo (Active Campaigns)
  - Icon circle, title, value, sub-indicator
- [x] **(F)** Buat `components/ui/transaction-list-item.blade.php`
  - Icon circle (Income: green bg, Expense: muted bg)
  - Transaction name + category tag
  - Amount (+ green / - dark) + time
- [x] **(F)** Buat `components/ui/market-alert-card.blade.php`
  - Icon + Title + Description body
  - Variants: Demand Spike, Promo Suggestion
- [x] **(F)** Buat `components/form/input.blade.php`, `button.blade.php`, `select.blade.php`

#### Hub Dashboard (N)
- [x] **(N)** Buat `HubController.php` (index + stats API)
- [x] **(N)** Buat `hub/dashboard.blade.php` — sesuai Figma 2:58
  - Welcome Section: "Halo, {business_name}" + health badge
  - Quick Stats Grid (3 bento cards): SiKas, SiPasar, SiPromo
  - Activity Feed: Recent Transactions (2/3 width) + Market Alerts (1/3 width)
- [x] **(N)** Query Supabase untuk summary data (revenue, market score, active campaigns)
- [x] **(N)** Buat mobile variant hub dashboard — sesuai Figma 2:1338

---

## Phase 4: SiKas — Core Financial Features 💰
> **Sprint 3-4** | **Target: Minggu 4-5** | **~91 SP**

### Definition of Done — Phase 4
- [ ] CRUD transaksi (manual) berfungsi end-to-end
- [ ] Voice input → transcribe → categorize → save transaction berfungsi
- [ ] Transaction history dengan filter (date, type, category) berfungsi
- [ ] Transaction detail panel berfungsi
- [ ] Laporan keuangan (chart + summary) ditampilkan
- [ ] Export PDF/CSV laporan berfungsi
- [ ] QRIS sync job berfungsi (minimal mock)

### Tasks

#### Backend — Transactions (N)
- [ ] **(N)** Buat Eloquent Models: Transaction, Category, Report
- [ ] **(N)** Buat `TransactionController.php` (index, store, show, update, destroy)
- [ ] **(N)** Buat `TransactionRequest.php` validation rules
- [ ] **(N)** Buat composite indexes di Supabase (umkm_id + outlet_id + transaction_date)
- [ ] **(N)** Buat `ReportAggregationService.php` (daily, weekly, monthly aggregates)
- [ ] **(N)** Buat `ExportService.php` (PDF + CSV generation)
- [ ] **(N)** Buat `ReportController.php` (index + export)
- [ ] **(N)** Buat rate limiting untuk voice ingest endpoint

#### Backend — AI Integration (N)
- [ ] **(N)** Buat `WhisperSTTService.php` (audio → text)
- [ ] **(N)** Buat `TransactionCategorizerService.php` (text → category + amount + metadata via Gemini)
- [ ] **(N)** Buat `FinancialInsightService.php` (AI-generated insights untuk transaction detail)
- [ ] **(N)** Buat noise-robust voice parsing (Bahasa Indonesia colloquial handling)
- [ ] **(N)** Buat `VoiceInputController.php` (transcribe + categorize endpoints)

#### Backend — QRIS (N)
- [ ] **(N)** Buat `QrisSyncService.php` (scheduled job for QRIS transaction reconciliation)
- [ ] **(N)** Buat `QrisSyncController.php`

#### Frontend — SiKas Views (F)
- [ ] **(F)** Buat `sikas/dashboard.blade.php` — sesuai Figma 2:922
  - "Dasbor Keuangan" heading + greeting
  - Input Suara Pintar (voice input bar with mic button)
  - Summary cards: Total Saldo, Penghematan, Target Cuan
  - Tren Pendapatan & Pengeluaran (line chart)
  - Rekomendasi AI SiKas card
  - Riwayat Terakhir (quick transaction list)
- [ ] **(F)** Buat `components/ui/voice-input-bar.blade.php`
  - Mic button (animated pulse when recording)
  - Waveform visualization (Alpine.js + Web Audio API)
  - Text preview of transcribed text
  - "CATAT TRANSAKSI SUARA" CTA
- [ ] **(F)** Buat `sikas/transactions.blade.php` — sesuai Figma 2:1443
  - Page header: "Transaction History" + description
  - Utility bar (glassmorphism): search + filter chips (Date Range, Type, Category)
  - Transaction list (grouped by date, e.g., "TODAY, OCT 24")
  - Selected item indicator (left 4px accent border)
  - Transaction Detail panel (right side):
    - Amount + status (Completed)
    - Details table (Date, Merchant, Category, Payment Method)
    - SiKas Insights AI card
    - Edit + Receipt buttons
- [ ] **(F)** Buat `components/ui/filter-chip.blade.php` (icon + label + dropdown)
- [ ] **(F)** Buat `components/ui/transaction-detail.blade.php`
- [ ] **(F)** Buat `components/ui/revenue-chart.blade.php` (Chart.js / Apex Charts)
  - Masuk vs Keluar line chart
  - 7 hari terakhir toggle
- [ ] **(F)** Buat manual transaction form (modal/slide-over)
  - Amount, description, type (income/expense), category, payment method, date
- [ ] **(F)** Buat `sikas/reports.blade.php` — sesuai Figma 2:1767
  - Report summary cards
  - Chart visualizations
  - Export buttons (PDF / CSV)
- [ ] **(F)** Buat Alpine.js komponen: `voice-input.js` (MediaRecorder API + waveform)
- [ ] **(F)** Buat mobile SiKas dashboard — sesuai Figma 2:693

---

## Phase 5: SiPasar — Market Research Features 🗺️
> **Sprint 5-6** | **Target: Minggu 6-7** | **~88 SP**

### Definition of Done — Phase 5
- [ ] Input lokasi + radius selector berfungsi
- [ ] Mapbox menampilkan peta dengan competitor pins
- [ ] Competitor list cards ditampilkan
- [ ] Demographic data panel berfungsi
- [ ] Market Fit Score ditampilkan dan benar
- [ ] "Analisis Ulang" flow berfungsi
- [ ] Data caching untuk analisis results

### Tasks

#### Backend — Data Pipeline (N)
- [ ] **(N)** Buat `GeospatialService.php` (coordinate queries, radius calculations)
- [ ] **(N)** Buat `CompetitorScraperService.php` (Playwright + Firecrawl integration)
- [ ] **(N)** Buat `BPSDataService.php` (BPS Open Data API + OSM demographic ingestion)
- [ ] **(N)** Buat `AnalysisCacheService.php` (cache results di Supabase, TTL-based)
- [ ] **(N)** Buat `AnalysisController.php` (index, analyze, results)
- [ ] **(N)** Buat `CompetitorController.php` (index)
- [ ] **(N)** Buat `DemographicController.php` (index)
- [ ] **(N)** Buat `ProfileCompletenessGate` (SiPasar requires complete profile)

#### Backend — AI Analysis (N)
- [ ] **(N)** Buat `SentimentTaggingService.php` (competitor sentiment: positive/neutral/negative)
- [ ] **(N)** Buat `MarketFitScoreService.php` (0-100 score calculation via AI)
- [ ] **(N)** Buat `TrendDetectionService.php` (clustering & trend discovery)
- [ ] **(N)** Buat `LocalizedInsightExplainerService.php` (insights dalam Bahasa Indonesia sederhana)

#### Frontend — SiPasar Views (F)
- [ ] **(F)** Buat `sipasar/landing.blade.php` — sesuai Figma 2:1184
  - Search bar: lokasi input ("Kebayoran Baru, Jakarta")
  - Category filter pills: Food & Beverage, Retail, Services
  - Map preview with density overlay
  - Market Fit Score card bottom sheet
- [ ] **(F)** Buat Mapbox integration (`alpine/map.js`)
  - Initialize Mapbox GL JS
  - Competitor pins with tooltips
  - Radius circle visualization
  - "F&B Density: Low/Medium/High" badge overlay
- [ ] **(F)** Buat `components/ui/market-fit-score-card.blade.php`
  - Score (0-100) + label (Excellent/Good/Fair)
  - Analysis Radius display
  - Competitors count + Population Density
  - "Generate Detailed Report" CTA
- [ ] **(F)** Buat competitor list cards (name, rating, review count, sentiment tag)
- [ ] **(F)** Buat demographic chart panel (population, income, age distribution)
- [ ] **(F)** Buat "Analisis Ulang" flow (re-run analysis with different parameters)
- [ ] **(F)** Buat `sipasar/results.blade.php` — sesuai Figma 2:2321 (detailed results)

---

## Phase 6: SiPromo — Creative Marketing Features 🎨
> **Sprint 7-8** | **Target: Minggu 8-9** | **~128 SP**

### Definition of Done — Phase 6
- [ ] Template gallery menampilkan content types (Social Media, Ad Copy, Blog, Email)
- [ ] Content brief form → AI generation → preview berfungsi
- [ ] Image generation via Flux Schnell berfungsi
- [ ] Caption & hashtag auto-generation berfungsi
- [ ] Content dapat di-share/download
- [ ] Publish ke Instagram/Facebook via Meta OAuth berfungsi
- [ ] Content history grid menampilkan semua generated content
- [ ] Copilot Bar berfungsi cross-module

### Tasks

#### Backend — Content Generation (N)
- [ ] **(N)** Buat `PromptOptimizationService.php` (optimize user prompt untuk AI)
- [ ] **(N)** Buat `FluxSchnellService.php` (image generation API)
- [ ] **(N)** Buat `CaptionGeneratorService.php` (caption + hashtag via Gemini)
- [ ] **(N)** Buat `BrandGuardService.php` (ensure brand consistency)
- [ ] **(N)** Buat `ProductPhotoEnhancementService.php` (enhance uploaded photos)
- [ ] **(N)** Buat `ContentController.php` (index, preview, history)
- [ ] **(N)** Buat `GenerateController.php` (show, create, apiGenerate, apiGenerateImage)
- [ ] **(N)** Buat `ContentRequest.php` validation
- [ ] **(N)** Buat Eloquent Models: ContentAsset, PublishJob

#### Backend — Publishing (N)
- [ ] **(N)** Buat `MetaOAuthService.php` (Facebook/Instagram OAuth flow)
- [ ] **(N)** Buat `PublishSchedulerService.php` (scheduled publishing)
- [ ] **(N)** Buat `PublishController.php` (publish action)
- [ ] **(N)** Buat `SupabaseStorageService.php` (upload generated images)
- [ ] **(N)** Buat webhook orchestration (Make/n8n integration boilerplate)

#### Backend — Copilot (N)
- [ ] **(N)** Buat `CopilotService.php` (agent orchestrator — cross-module AI assistant)
- [ ] **(N)** Buat `CopilotController.php` (ask + stream endpoints)

#### Frontend — SiPromo Views (F)
- [ ] **(F)** Buat `sipromo/landing.blade.php` — sesuai Figma 2:2069
  - "SiPromo AI: Generative Marketing" badge
  - "Welcome to SiPromo." hero heading
  - Prompt input bar (textarea + content type selector: Social Media, Ad Copy, Blog Post)
  - AI Model selector dropdown
  - Suggestion chips ("Instagram carousel for a local batik brand...", etc.)
  - Recent Generations carousel (image cards with type badge: IG, Ads, Blog, Email)
- [ ] **(F)** Buat `sipromo/generate.blade.php` — sesuai Figma 2:2251
  - Content brief form
  - Image upload + preview
  - Generate button
- [ ] **(F)** Buat `sipromo/preview.blade.php` — sesuai Figma 2:2561
  - Campaign title + "Share" + "Download" buttons
  - Main image preview (large)
  - Thumbnail carousel (10 slides)
  - Creative Brief sidebar:
    - Product Name, Tone & Style tags, Generated Copy
  - AI Refinement:
    - Text area ("Describe what you'd like to change")
    - "Regenerate Concept" button
  - "Finalize & Export ✓" CTA button
- [ ] **(F)** Buat `components/ui/content-preview-card.blade.php`
- [ ] **(F)** Buat `sipromo/history.blade.php` (Riwayat Konten Grid)
- [ ] **(F)** Buat caption & hashtag editor UI
- [ ] **(F)** Buat social publish selector (Instagram/Facebook toggle)
- [ ] **(F)** Buat `components/ui/copilot-bar.blade.php`
  - Floating bottom bar, expandable
  - Chat-like interface
  - Cross-module context awareness
- [ ] **(F)** Buat Alpine.js komponen: `copilot.js` (streaming chat, command parser)

---

## Phase 7: SiStok — Inventory Management 📦
> **Sprint 8** | **Target: Minggu 9** | **~15 SP**

### Definition of Done — Phase 7
- [ ] Product CRUD berfungsi
- [ ] Product table dengan filter/search berfungsi
- [ ] Stock level monitoring dan alerts berfungsi
- [ ] Pagination berfungsi

### Tasks

- [ ] **(N)** Buat Eloquent Model: Product
- [ ] **(N)** Buat `ProductController.php` (CRUD + search + filter)
- [ ] **(F)** Buat `sistok/index.blade.php` — sesuai Figma 2:1767
  - Header: "SiStok" + "Manage your product catalog" + Filter + Add Product CTA
  - Summary cards: Total Products, Low Stock Items (action required), Est. Inventory Value
  - Category tabs: All Products, Textiles (Batik), Handicrafts, Food & Bev
  - Product table: Image, Product Name + SKU, Category, Price, Stock Level, Status badge, Edit action
  - Pagination: "Showing 1-10 of 342" + page buttons
- [ ] **(F)** Buat `components/ui/product-table.blade.php`
- [ ] **(N)** Buat stock alert logic (low stock threshold → notification)

---

## Phase 8: Quality, Testing & TAM Evaluation ✅
> **Sprint 9** | **Target: Minggu 10** | **~29 SP**

### Definition of Done — Phase 8
- [ ] Unit tests coverage ≥ 70% untuk services kritis
- [ ] Feature tests untuk semua controller endpoints
- [ ] E2E tests (Playwright) untuk happy paths
- [ ] AI prompt regression tests pass
- [ ] TAM survey form & result dashboard terbuat

### Tasks

#### Testing (N)
- [ ] **(N)** Setup Laravel Test Suite (PHPUnit)
- [ ] **(N)** Buat unit tests: SupabaseAuthService
- [ ] **(N)** Buat unit tests: TransactionCategorizerService
- [ ] **(N)** Buat unit tests: ReportAggregationService
- [ ] **(N)** Buat unit tests: MarketFitScoreService
- [ ] **(N)** Buat feature tests: Auth flow (login, register, logout)
- [ ] **(N)** Buat feature tests: Transaction CRUD
- [ ] **(N)** Buat feature tests: Report generation & export
- [ ] **(N)** Buat AI prompt regression harness (versioned prompts + expected outputs)

#### E2E Testing (F)
- [ ] **(F)** Setup Playwright
- [ ] **(F)** Buat E2E: Login → Dashboard → Logout happy path
- [ ] **(F)** Buat E2E: Create transaction (manual) → Verify in history
- [ ] **(F)** Buat E2E: SiPasar analysis flow
- [ ] **(F)** Buat E2E: SiPromo content generation → preview → download

#### TAM Survey (F + N)
- [ ] **(F)** Buat TAM survey form (embedded in app or separate page)
- [ ] **(N)** Buat survey result dashboard / aggregation

---

## Phase 9: Deployment & Documentation 🚀
> **Sprint 10** | **Target: Minggu 11** | **~19 SP**

### Definition of Done — Phase 9
- [ ] Aplikasi deployed dan accessible via public URL
- [ ] SSL/HTTPS aktif
- [ ] Error monitoring (Sentry) terkonfigurasi
- [ ] User documentation lengkap
- [ ] API reference tersedia
- [ ] Demo credentials tersedia untuk reviewer

### Tasks

#### Deployment (N)
- [ ] **(N)** Setup deployment pipeline (Railway / Forge / manual VPS)
- [ ] **(N)** Konfigurasi production `.env` (Supabase production, AI API keys)
- [ ] **(N)** Setup Sentry error monitoring
- [ ] **(N)** Konfigurasi Laravel queue worker untuk background jobs (QRIS sync, scraper, publish)
- [ ] **(N)** Setup scheduled tasks (Laravel Scheduler) untuk:
  - QRIS sync job (hourly)
  - Report auto-generation (daily)
  - Publish scheduler (per-schedule)
  - Analysis cache cleanup (daily)
- [ ] **(N)** AI model version pinning & fallback strategy documentation

#### Frontend Build (F)
- [ ] **(F)** Production build `npm run build` → verify semua assets
- [ ] **(F)** Performance audit (Lighthouse score check)
- [ ] **(F)** Responsive final QA pada semua breakpoints
- [ ] **(F)** Final CSS/styling polish pass

#### Documentation (F + N)
- [ ] **(F)** Buat User Documentation (Bahasa Indonesia) — cara pakai setiap modul
- [ ] **(N)** Buat API Reference (endpoint list + request/response examples)
- [ ] **(N)** Buat demo credentials untuk reviewer
- [ ] **(F)** Buat README.md proyek (setup guide, tech stack, architecture overview)
- [ ] **(N)** Buat database schema documentation (ERD + table descriptions)

---

## Phase 10: Final Polish & Submission 🎯
> **Sprint 10** | **Target: Akhir Minggu 11**

### Definition of Done — Phase 10
- [ ] Semua fitur P0 berfungsi tanpa bug blocking
- [ ] UI pixel-perfect sesuai Figma
- [ ] All tests pass
- [ ] Demo ready & presentable

### Tasks

- [ ] **(F)** Final UI review vs Figma (pixel comparison)
- [ ] **(N)** Final data integrity check (no orphan records, RLS working)
- [ ] **(F)** Fix semua UI bugs/polish items
- [ ] **(N)** Fix semua backend bugs/edge cases
- [ ] **(F + N)** Dry run demo presentation
- [ ] **(F + N)** Record demo video (opsional)
- [ ] **(F + N)** Final deployment ke production
- [ ] **(F + N)** Submit proyek

---

## Summary — Pembagian Tugas

| Developer | Fokus Utama | Estimasi Beban |
|---|---|---|
| **Fardan (F)** | Frontend (Blade, Tailwind, Alpine.js, UI/UX), E2E Testing, Documentation | ~50% total tasks |
| **Nafi (N)** | Backend (Laravel Controllers, Services, Supabase, AI Integration), Database, Unit Testing, Deployment | ~50% total tasks |

### Prinsip Pembagian:
- **Fardan** → semua yang dilihat user: UI, views, components, styling, client-side JS, E2E
- **Nafi** → semua yang di belakang layar: database, auth, API, AI services, scraping, deployment
- Beberapa task **overlap** (routes, profile) untuk koordinasi

---

## Quick Reference — Figma Node → Page Mapping

| Figma Node | Page Name | Platform |
|---|---|---|
| `2:2` | Login (Desktop) | Desktop |
| `2:573` | Login (Mobile) | Mobile |
| `2:269` | Register (Mobile) | Mobile |
| `2:58` | SiJual Hub Dashboard | Desktop |
| `2:1337` | Hub Frame (empty) | — |
| `2:1338` | Hub Dashboard Mobile | Mobile |
| `2:416` | Onboarding / SiKas Mobile Dashboard | Mobile |
| `2:922` | SiKas Dashboard | Desktop |
| `2:693` | SiKas Mobile | Mobile |
| `2:1443` | SiKas Transaction History | Desktop |
| `2:1767` | SiKas Reports / SiStok | Desktop |
| `2:1184` | SiPasar Landing | Mobile |
| `2:837` | SiPasar Analysis | Mobile |
| `2:2321` | SiPasar Results | Desktop |
| `2:2069` | SiPromo Landing | Desktop |
| `2:2251` | SiPromo Generate | Desktop |
| `2:2561` | SiPromo Preview | Desktop |
