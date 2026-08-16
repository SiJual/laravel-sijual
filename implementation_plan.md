# SiPasar UI Redesign (Map-Centric Interface)

Berdasarkan keluhan Anda dan referensi gambar yang diberikan, saat ini `SiPasar` hanya menampilkan formulir riset standar berjejer dengan kartu hasil analisis, sehingga membuang potensi UX spasialnya. Sesuai visi awal, halaman ini seharusnya terasa seperti sebuah **"Command Center Spasial"** dengan peta Mapbox yang membentang menutupi seluruh layar (*full-bleed*), sementara komponen *Market Filters* dan *Market Fit Score* melayang (*floating*) di atasnya.

## User Review Required

> [!IMPORTANT]
> Karena kita mengubah `landing.blade.php` menjadi layar penuh tanpa *scroll* berlebihan (peta sebagai *background*), *layout* dasar aplikasi (`app.blade.php`) perlu saya sesuaikan agar kontainer halamannya bisa mendukung mode *absolute/full-screen map*.
> Selain itu, karena menggunakan **Mapbox GL JS**, Anda harus mendaftar akun Mapbox secara gratis untuk mendapatkan `MAPBOX_API_KEY` (jika belum punya) yang nantinya harus dipasang di *file* `.env`.

## Proposed Changes

### 1. Konfigurasi Lingkungan (Environment)
#### [MODIFY] [.env.example](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/.env.example)
- Menambahkan baris konfigurasi `MAPBOX_API_KEY=` agar developer lain (dan tim *deploy*) tahu bahwa variabel ini wajib diisi.

### 2. Modifikasi Layout Global
#### [MODIFY] [app.blade.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/resources/views/layouts/app.blade.php) (atau [components/layouts/app.blade.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/resources/views/components/layouts/app.blade.php))
- Menambahkan pengecekan *class* opsional. Jika rutenya adalah SiPasar, *container* utamanya (`<main>`) tidak akan diberi batasan lebar (agar peta bisa mengisi 100% *viewport* dari ujung sidebar sampai ujung layar).

### 3. Rombak Total Halaman SiPasar Landing
#### [MODIFY] [landing.blade.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/resources/views/sipasar/landing.blade.php)
- **Mapbox Container**: Membuat div `#map` yang bersifat `absolute inset-0` di belakang semua elemen.
- **Mapbox JS & CSS**: Menambahkan skrip CDN `mapbox-gl.js` dan CSS-nya secara spesifik di halaman ini (menggunakan stack/push scripts).
- **Floating Market Filters**: Membungkus form "Lokasi Target Riset" dan "Radius" dalam komponen kartu dengan kelas `absolute top-6 left-6 z-10 w-80 bg-white/95 backdrop-blur shadow-lg rounded-xl`.
- **Floating Market Fit Score**: Jika `$latestAnalysis` ada, skor tersebut akan dimunculkan dalam kartu kecil di posisi `absolute top-6 right-6 z-10`.
- **Peta Interaktif (JS)**: Menambahkan skrip inisialisasi Mapbox yang menggunakan `MAPBOX_API_KEY` dari konfigurasi `config('services.mapbox.key')`, lalu memposisikan kamera peta ke titik koordinat terakhir profil/analisis.

### 4. Tambah Konfigurasi Layanan Eksternal
#### [MODIFY] [config/services.php](file:///c:/Kuliah/Programming/%21%20Hackathon/sijual/config/services.php)
- Mendaftarkan *key* mapbox:
  ```php
  'mapbox' => [
      'key' => env('MAPBOX_API_KEY'),
  ],
  ```

## Verification Plan

### Automated Tests
- Meninjau ketersediaan struktur DOM baru (`#map`) dan elemen absolut.

### Manual Verification
- Anda perlu menyalin nilai `MAPBOX_API_KEY` ke `.env` lokal Anda lalu me-*refresh* halaman SiPasar untuk melihat secara langsung apakah peta sudah muncul sebagai *background* layar penuh dan formulir riset melayang dengan posisi yang tepat sesuai gambar referensi Anda.

Tolong periksa rencana ini. Jika Anda menyetujui pendekatannya, saya akan segera mengeksekusi semua perubahan *file* tersebut!
