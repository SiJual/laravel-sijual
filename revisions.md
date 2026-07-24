# SiJual — Revisions Log

## Revisi Skema Database (Audit 2026-07-24) — ✅ SELESAI

Berdasarkan audit mendalam terhadap 17 file SQL migrasi vs PRD dan Eloquent Models, ditemukan 6 masalah skema. Seluruhnya telah diperbaiki.

### Perbaikan yang Telah Dieksekusi:

- [x] **Fix #1: Duplikasi nomor migrasi `016_`**
  - `016_create_storage_rls_policies.sql` → di-rename menjadi `017_create_storage_rls_policies.sql`

- [x] **Fix #2: Tabel `reports` — kolom agregasi fisik**
  - Ditambahkan kolom `total_income`, `total_expense`, `net_profit`, `transaction_count` (BIGINT/INT)
  - Model `Report.php` di-update (`$fillable` + `$casts`)

- [x] **Fix #3: Tabel `demographics` — relasi ke `market_analyses`**
  - Ditambahkan kolom `analysis_id UUID REFERENCES market_analyses(id)`
  - Model `Demographic.php` di-update (`$fillable` + relasi `analysis()`)
  - Model `MarketAnalysis.php` di-update (relasi `demographics()`)

- [x] **Fix #4: Auth sync trigger (`auth.users` → `public.users`)**
  - Dibuat function + trigger `on_auth_user_created` (INSERT) dan `on_auth_user_updated` (UPDATE)

- [x] **Fix #5: UNIQUE constraint pada `umkm_profiles.user_id`**
  - Ditambahkan `UNIQUE (user_id)` untuk menjamin relasi one-to-one

- [x] **Fix #6: CHECK constraint pada `products.category`**
  - Nilai dibatasi ke: `textiles`, `handicrafts`, `food_bev`, `services`, `other`

### File yang Di-modify / Dibuat:

| File | Aksi |
|---|---|
| `database/supabase/018_schema_patch_audit.sql` | **BARU** — SQL patch gabungan |
| `database/supabase/017_create_storage_rls_policies.sql` | **RENAME** dari `016_` |
| `app/Models/Report.php` | **UPDATE** — `$fillable` + `$casts` |
| `app/Models/Demographic.php` | **UPDATE** — `$fillable` + relasi `analysis()` |
| `app/Models/MarketAnalysis.php` | **UPDATE** — relasi `demographics()` |

> [!IMPORTANT]
> File `018_schema_patch_audit.sql` perlu dieksekusi manual di **Supabase SQL Editor** karena kita tidak menggunakan Laravel Migrations untuk Supabase.
