# SiJual — Hasil Review Phase 1 & Revisions

Berikut adalah hasil review pengerjaan Phase 1 (Environment & Project Setup). Secara keseluruhan pekerjaan sudah bagus dan mengikuti `procedure.md` dengan baik. Namun, ada beberapa poin revisi dan masukan untuk menjaga skalabilitas (scalability) proyek ke depannya.

## 📝 Daftar Revisi (Phase 1)

**Status Keseluruhan:** Sebagian besar task Phase 1 selesai dengan baik.
Mohon kerjakan poin-poin berikut sebelum melanjutkan penuh ke Phase 2.

- [ ] **Task Rev 1.1: Bersihkan font bawaan di `vite.config.js`**
  - **Keterangan:** Di `vite.config.js`, masih terdapat import dan konfigurasi `bunny('Instrument Sans')` bawaan Laravel. Karena kita sudah memuat Google Fonts (Noto Serif & Plus Jakarta Sans) secara manual di layout Blade, hapus plugin `laravel-vite-plugin/fonts` agar tidak meload font ganda dan membebani performa.

- [ ] **Task Rev 1.2: Lengkapi metode di `GeminiService.php`**
  - **Keterangan:** File `app/Services/AI/GeminiService.php` sudah dibuat beserta fallback-nya. Namun, metode `categorizeTransaction` yang ada di `procedure.md` (Phase 4) belum ditambahkan. Walaupun spesifik untuk Phase 4, ada baiknya struktur method-nya (beserta prompt JSON) disiapkan dari sekarang sesuai referensi di prosedur, agar service AI ini siap dipakai oleh Controller nantinya.

- [ ] **Task Rev 1.3: Tangani Component yang Belum Ada di Base Layouts**
  - **Keterangan:** Di `layouts/app.blade.php`, pemanggilan komponen seperti `<x-navigation.side-nav-bar>` sudah diletakkan. Perlu diingat bahwa komponen ini baru akan dibuat pada **Phase 3**. Ini wajar untuk persiapan, tetapi jika view dirender sekarang, Laravel akan error. Pastikan komponen dummy atau placeholder segera dibuat saat memasuki Phase 3 agar halaman bisa dicek di browser.

## 🚀 Masukan Skalabilitas (Scalability Flow) ke Depan

Agar platform **SiJual** ini bisa di-maintain dengan mudah saat jumlah UMKM membesar, perhatikan flow berikut dalam pengerjaan fase-fase berikutnya:

1. **Strict Type Hinting & Return Types**
   Pastikan setiap controller dan service class memiliki *type hinting* yang jelas (baik parameter maupun return type). Contoh: `public function categorize(string $text): array`. Ini sangat mempermudah debugging jika AI gagal mem-parsing format.

2. **Repository / Service Pattern Consistency**
   Jangan menulis business logic (seperti perhitungan Market Score atau pembuatan prompt Gemini yang kompleks) di dalam Controller. Semua logic harus dienkapsulasi di dalam kelas-kelas `App\Services`. Controller hanya bertugas memvalidasi request (`FormRequest`) dan mengembalikan respons.

3. **Background Jobs (Queues)**
   Untuk Phase 4 (QRIS Sync) dan Phase 6 (AI Image Generation & Publishing), *response time* API sangat bergantung pada pihak ketiga. AI bawahan **wajib** menggunakan fitur Queue Laravel (`ShouldQueue`). Jangan biarkan user menunggu loading spinner selama puluhan detik saat men-generate gambar Flux. Dispatch job, kembalikan status `processing`, lalu perbarui UI dengan *polling* atau Alpine.js.

4. **Robust AI Prompting**
   Saat bekerja dengan Gemini dan OpenAI, selipkan *fallback JSON format* dan *error handling* jika output AI tidak sesuai struktur. Gunakan validasi `json_decode()` yang aman dan berikan *default values* jika parsing gagal, agar aplikasi tidak crash.

5. **RLS (Row Level Security) Validations**
   Supabase RLS adalah kunci keamanan *multi-tenant* kita. Pastikan setiap migration table untuk Phase 2 benar-benar menyertakan filter `user_id` atau `umkm_id` sehingga satu UMKM tidak akan bisa secara tidak sengaja membaca/mengedit transaksi UMKM lain.

Silakan centang item di atas pada file ini setelah diperbaiki, dan jadikan panduan "Skalabilitas" ini sebagai pegangan wajib saat mengeksekusi Phase selanjutnya.
