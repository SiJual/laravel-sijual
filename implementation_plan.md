# Rencana Perbaikan Tambahan (Missed Items dari final_lists.md)

Mohon maaf, Anda benar. Pada iterasi sebelumnya saya hanya berfokus pada 12 *checklist* terbawah dan melewatkan poin-poin *Medium Priority* dan catatan spesifik lainnya yang dijabarkan dalam badan dokumen laporan tester Anda. Saya telah membaca ulang laporan tersebut dan merumuskan perbaikan untuk ke-8 poin krusial yang tersisa tanpa terkecuali.

## User Review Required

> [!IMPORTANT]
> **Issue #3 (Auth Provider):** Saat ini seluruh aplikasi membaca *session* `supabase_user` secara manual. Mengubah ini menjadi *Custom User Provider* (agar `auth()->user()` bawaan Laravel berfungsi) adalah praktik terbaik (*best practice*). Saya akan mengimplementasikan `SupabaseUserProvider` yang membaca *session* tersebut, sehingga kita bisa mengembalikan fungsionalitas `auth()->user()` tanpa merusak kode *controller* yang sudah menggunakan *session*.

## Proposed Changes

### 1. [Backend] Fix Auth Provider (Issue #3)
#### [NEW] [SupabaseUserProvider.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Providers/SupabaseUserProvider.php)
- Membuat *provider* otentikasi kustom untuk Laravel yang mengambil profil pengguna dari `session('supabase_user')` dan mengembalikan model `User`.
#### [MODIFY] [AuthServiceProvider.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Providers/AuthServiceProvider.php)
- Meregistrasikan `SupabaseUserProvider` ke dalam *Auth facade* Laravel.
#### [MODIFY] [auth.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/config/auth.php)
- Mengubah konfigurasi *guards/providers* agar menggunakan `supabase` *driver* yang baru dibuat.

### 2. [Security] RLS Storage Celah Logika (Issue #11)
#### [NEW] [019_patch_storage_rls_service.sql](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/database/supabase/019_patch_storage_rls_service.sql)
- Menambahkan kriteria `OR auth.role() = 'service_role'` pada aturan RLS *Storage* Supabase. Ini memastikan ketika layanan AI *backend* kita mengunggah gambar menggunakan kunci API utama (*Service Role*), manipulasi datanya tidak akan diblokir oleh RLS karena `owner` terdeteksi `NULL`.

### 3. [Backend] Timezone Desync (Issue #15)
#### [MODIFY] [PublishController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/Social/PublishController.php)
- Memaksa parsing waktu penjadwalan agar berpatokan pada zona waktu Indonesia: `Carbon::parse($request->scheduled_at, 'Asia/Jakarta')->setTimezone('UTC');`. Ini memperbaiki masalah sinkronisasi bagi pengguna di WITA/WIT.

### 4. [Backend] Data Desynchronization Outlet (Issue #18)
#### [MODIFY] [ProfileController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/Profile/ProfileController.php)
- Menambahkan logika pembaruan (*update*) pada `Outlet` pusat (yang memiliki `is_primary = true`) agar `name` dan `address`-nya tersinkronisasi setiap kali *user* mengedit profil tokonya.

### 5. [Backend] Integer Overflow Product (Issue #19)
#### [MODIFY] [ProductController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/SiStok/ProductController.php)
- Mengamankan operasi matematika dari potensi nilai yang sangat besar di *database*: mengubah `selectRaw('SUM(price * stock_level)')` menjadi `selectRaw('CAST(SUM(price * stock_level) AS BIGINT)')`.

### 6. [Backend] Missing Image URL Validation (Issue #20)
#### [MODIFY] [ProductController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/SiStok/ProductController.php)
- Menambahkan validasi `'image_url' => 'nullable|url|max:2048'` pada metode `store` dan memasukannya ke dalam operasi `Product::create()` untuk mencegah injeksi *base64 payload*.

### 7. [Backend] Unreachable Code (Issue #21)
#### [MODIFY] [HubController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/Dashboard/HubController.php)
- Menghapus pembungkus blok *if* `if ($profile)` yang berlebihan. Hal ini lantaran rute tersebut sudah dilindungi *middleware* `profile.complete` yang memberikan jaminan 100% bahwa `$profile` selalu ada.

### 8. [Backend] Hardcoded Fake UI State (Issue #22)
#### [MODIFY] [HubController.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/app/Http/Controllers/Dashboard/HubController.php)
- Mengubah kondisi patokan skor *hardcode* 85 (`$latestMarketScore = $latestAnalysis ? ... : 85`) menjadi `null` atau `0`. Ini menghilangkan status palsu/bodong jika UMKM belum pernah melakukan riset pasar SiPasar sama sekali.

## Verification Plan

### Automated Tests
- Menjalankan `php artisan test` lagi, namun saya sadar bahwa driver `sqlite` absen pada sistem operasi lokal Anda. Jadi verifikasi akan bergantung pada pengamatan logika kode (Static Code Analysis).

### Manual Verification
- Anda bisa memverifikasi langsung di Supabase SQL Editor dengan menjalankan skrip `019_`.
- Mengedit Nama UMKM di menu Profil dan melihat apakah Cabang Pusat di SiKAS ikut berganti nama.

Tolong berikan persetujuan jika Anda merasa rancangan ini sudah akurat, lengkap, dan menjawab kekurangan yang tim tester sampaikan!
