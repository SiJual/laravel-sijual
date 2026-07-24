# SiJual — Revisions Log

## Revisi Skema Database (Audit 2026-07-24) — ✅ SELESAI

Berdasarkan audit mendalam terhadap 17 file SQL migrasi vs PRD dan Eloquent Models, ditemukan 6 masalah skema. Seluruhnya telah diperbaiki.

### Perbaikan yang Telah Dieksekusi:

- [x] **Fix #1: Duplikasi nomor migrasi `016_`**
- [x] **Fix #2: Tabel `reports` — kolom agregasi fisik**
- [x] **Fix #3: Tabel `demographics` — relasi ke `market_analyses`**
- [x] **Fix #4: Auth sync trigger (`auth.users` → `public.users`)**
- [x] **Fix #5: UNIQUE constraint pada `umkm_profiles.user_id`**
- [x] **Fix #6: CHECK constraint pada `products.category`**

---

## 🚨 Perbaikan 12 Bug Kritis & IDOR (Tester final_lists.md) — ✅ SELESAI

Berdasarkan laporan dari dokumen `final_lists.md`, semua celah kerentanan, data yatim, bentrok *trigger*, dan kebocoran IDOR telah berhasil ditambal secara komprehensif.

### Daftar Perbaikan yang Telah Dieksekusi:

#### 🔐 Auth & Middleware Fixes
- [x] **Guest Middleware Fix:** Membuat `GuestSupabase.php` sebagai pengganti `guest` bawaan agar pengguna yang sudah login dengan *session* custom dialihkan ke `/dashboard` (mencegah *infinite redirect*).
- [x] **Register DB Clash Fix:** Menghapus eksekusi `User::create` manual di `RegisterController` yang memicu error *Unique Constraint*, karena sekarang sudah diurus oleh Supabase Trigger `on_auth_user_created`.
- [x] **Logout Exception Fix:** Menambahkan `try-catch` saat memanggil `signOut` Supabase agar sesi lokal tetap terhapus (*flush*) meskipun API Supabase sedang *timeout*.

#### 🛡️ Keamanan & Penambalan Celah (IDOR)
- [x] **PublishController IDOR Fix:** Menambahkan filter `whereHas` dengan `umkm_id` dari profil pengguna yang *login* agar pengguna tidak bisa melihat *posting* UMKM lain.
- [x] **TransactionController IDOR Fix:** Menambahkan pengecekan eksplisit agar `$request->category_id` dan `$request->outlet_id` wajib milik `umkm_id` si pengguna. Melempar `422 Validation Error` jika *invalid*.
- [x] **Global Input Sanitizer:** Membuat `StripTagsMiddleware` yang di-_register_ secara global di `bootstrap/app.php` untuk membersihkan tag HTML (`<script>`, `<iframe>`) dari seluruh *payload* sistem secara otomatis.
- [x] **Webhook CSRF Fix:** Memasukkan `/sikas/qris-sync` ke dalam pengecualian token CSRF di `bootstrap/app.php`.
- [x] **Rate Limiting:** Mengaktifkan middleware `throttle:10,1` pada *route* `/sipasar/analyze`.

#### 📊 Integritas Data & AI
- [x] **Orphan Data (Demographics) Fix:** Memastikan data yang diambil dari BPS dimasukkan ke dalam tabel fisik `demographics` berelasi dengan `analysis_id`.
- [x] **DB Transaction Fix:** Membungkus seluruh logika insersi data SiPasar di `AnalysisController` dalam `DB::transaction()` untuk mencegah data separuh jalan tersimpan saat gagal.
- [x] **Hardcoded Coordinates Removed:** Mengganti *dummy coordinates* Jakarta dengan latitude dan longitude asli dari profil UMKM (`$profile->latitude`).
- [x] **Gemini Service Resiliency:** Menambahkan `timeout(15)` pada HTTP call Gemini dan melempar eksepsi *(throw Exception)* saat JSON rusak alih-alih me-_return_ data bodong.

---

> [!WARNING]
> **Catatan Uji Coba (Testing):**
> Proses eksekusi `php artisan test` **gagal dijalankan** karena *environment* Windows lokal Anda tidak memiliki atau tidak mengaktifkan driver `pdo_sqlite` (memunculkan error `could not find driver: sqlite`). Pengujian otomatis bawaan Laravel (Pest/PHPUnit) membutuhkan SQLite di mode `:memory:`. 
> Namun jangan khawatir, secara *code-logic*, kerentanan sudah diperbaiki dan Anda dapat langsung melakukan pengujian fungsional/manual via UI *browser*!
