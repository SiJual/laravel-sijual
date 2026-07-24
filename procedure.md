# SiJual — Prosedur Eksekusi Proyek (AI Agent Playbook)

> **Dokumen ini adalah instruksi lengkap untuk AI agent yang ditugaskan mengerjakan proyek SiJual.**
> Baca SELURUH dokumen ini sebelum mulai mengerjakan task apapun.

---

## 🔴 ATURAN WAJIB — BACA PERTAMA

1. **JANGAN mengarang syntax.** Selalu gunakan Context7 MCP (`resolve-library-id` → `query-docs`) untuk memverifikasi syntax Laravel, Tailwind CSS v4, dan Supabase sebelum menulis kode.
2. **JANGAN mengubah tech stack.** Proyek ini menggunakan **Laravel + Tailwind CSS v4 + Supabase PostgreSQL**. Bukan Next.js, bukan Firebase, bukan React.
3. **JANGAN membuat file di luar folder proyek** (`c:\Kuliah\Programming\! Hackathon\sijual\`).
4. **SELALU baca file referensi** sebelum mengerjakan task:
   - `summary.md` → konteks proyek & design tokens
   - `implementation_plan.md` → arsitektur, database schema, folder structure, routing
   - `tasks.md` → checklist & status
5. **SELALU update `tasks.md`** setelah menyelesaikan sebuah task (ubah `[ ]` → `[x]`).
6. **SELALU gunakan Bahasa Indonesia** untuk UI text (labels, placeholders, error messages, notifikasi).
7. **JANGAN skip empty states, loading states, dan error states.** Setiap halaman harus handle ketiga kondisi ini.
8. **SELALU jalankan dan verifikasi** setelah membuat/mengubah kode. Minimal `php artisan serve` harus jalan tanpa error.

---

## 📋 KONTEKS PROYEK

### Apa itu SiJual?
SiJual adalah platform web **Assistive Intelligence** untuk UMKM Indonesia. Terdiri dari 4 modul:
- **SiKas** — Pencatatan keuangan pintar (voice input, AI categorization, QRIS sync)
- **SiPasar** — Riset pasar geodemografis (Mapbox, scraping kompetitor, BPS data)
- **SiPromo** — Pemasaran kreatif AI (content generation, image gen, auto-publish)
- **SiStok** — Manajemen inventori produk

### Tim
- **Fardan (F)** — Frontend: Blade views, Tailwind styling, Alpine.js, E2E tests
- **Nafi (N)** — Backend: Controllers, Services, Supabase integration, AI services, deployment

### Tech Stack FINAL
| Layer | Teknologi | Versi |
|---|---|---|
| Backend Framework | Laravel | Latest (13.x) |
| CSS Framework | Tailwind CSS | v4 (CSS-first config, `@tailwindcss/vite`) |
| Build Tool | Vite | via `laravel-vite-plugin` |
| Database | Supabase PostgreSQL | via Eloquent ORM (pgsql driver) |
| Auth | Supabase Auth | REST API + JWT |
| Storage | Supabase Storage | REST API |
| Client-side JS | Alpine.js | v3 |
| Charts | Chart.js atau ApexCharts | Latest |
| Maps | Mapbox GL JS | Latest |
| AI Primary | Google Gemini API | Latest |
| AI Fallback | OpenAI API | Latest |
| Image Gen | Flux Schnell | Self-hosted |
| STT | Whisper API | Latest |

---

## 🏗️ PROSEDUR PER-PHASE

---

### PHASE 1: Environment & Project Setup

#### Step 1.1 — Inisialisasi Laravel

```bash
# Pastikan di direktori proyek
cd "c:\Kuliah\Programming\! Hackathon\sijual"

# Buat proyek Laravel baru (jika belum ada)
composer create-project laravel/laravel . --prefer-dist

# Verifikasi
php artisan --version
```

> **PENTING:** Jika folder sudah berisi file (seperti summary.md, dll), pindahkan dulu ke tempat sementara, buat Laravel project, lalu kembalikan file-file tersebut.

#### Step 1.2 — Setup Tailwind CSS v4 + Vite

```bash
# Install Tailwind CSS v4 + Vite plugin
npm install tailwindcss @tailwindcss/vite
```

Edit `vite.config.js`:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

Edit `resources/css/app.css`:
```css
@import "tailwindcss";

@theme {
    /* === COLORS === */
    --color-primary: #9D3D2B;
    --color-primary-light: oklch(from #9D3D2B l c h / 0.1);
    --color-primary-subtle: oklch(from #9D3D2B l c h / 0.05);
    --color-surface: #FEF8F6;
    --color-surface-alt: #F8F2F0;
    --color-surface-warm: #FCF8F4;
    --color-background: #F4F1EA;
    --color-on-surface: #1D1B1A;
    --color-on-surface-variant: #56423E;
    --color-border: oklch(from #89726D l c h / 0.2);
    --color-border-input: #DDC0BA;
    --color-success: #506356;
    --color-success-bg: oklch(from #506356 l c h / 0.1);
    --color-success-surface: #D2E8D8;
    --color-error: #BA1A1A;
    --color-muted: #E7E1DF;
    --color-hero-accent: #FFDAD3;

    /* === FONTS === */
    --font-display: 'Noto Serif', serif;
    --font-body: 'Plus Jakarta Sans', sans-serif;

    /* === SHADOWS === */
    --shadow-sm: 0 1px 1px rgba(0,0,0,0.05);
    --shadow-card: 0 4px 10px rgba(45,43,42,0.05);
    --shadow-elevated: 0 4px 20px rgba(45,43,42,0.05);
    --shadow-hero: 0 8px 32px rgba(0,0,0,0.1);

    /* === RADIUS === */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-full: 9999px;
}
```

#### Step 1.3 — Google Fonts

Tambahkan di `resources/views/layouts/app.blade.php` dan `guest.blade.php`:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
```

#### Step 1.4 — Supabase Connection

Edit `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=xxxx.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.xxxx
DB_PASSWORD=your-database-password

SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-role-key

GEMINI_API_KEY=your-gemini-key
OPENAI_API_KEY=your-openai-key
```

Buat `config/supabase.php`:
```php
<?php

return [
    'url' => env('SUPABASE_URL'),
    'key' => env('SUPABASE_ANON_KEY'),
    'service_key' => env('SUPABASE_SERVICE_KEY'),
];
```

Buat `config/ai.php`:
```php
<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'flux' => [
        'endpoint' => env('FLUX_ENDPOINT', 'http://localhost:8080'),
    ],
    'whisper' => [
        'api_key' => env('WHISPER_API_KEY'),
    ],
];
```

#### Step 1.5 — Alpine.js

```bash
npm install alpinejs
```

Edit `resources/js/app.js`:
```javascript
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

#### Step 1.6 — Verifikasi Setup

```bash
npm run dev          # Vite dev server harus jalan
php artisan serve    # Laravel dev server harus jalan
# Buka http://localhost:8000 → harus muncul Laravel welcome page
```

---

### PHASE 2: Database & Authentication

#### Step 2.1 — Buat Semua SQL Migration Files

Buat folder `database/supabase/` dan isi dengan file SQL. Setiap file dijalankan di **Supabase SQL Editor** (Dashboard → SQL Editor → New Query → Paste → Run).

**POLA SETIAP TABEL:**
```sql
-- database/supabase/00X_create_xxx_table.sql

CREATE TABLE IF NOT EXISTS public.table_name (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    -- ... columns ...
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

-- Enable RLS
ALTER TABLE public.table_name ENABLE ROW LEVEL SECURITY;

-- RLS Policies (sesuaikan per tabel)
CREATE POLICY "policy_name" ON public.table_name
    FOR SELECT USING (/* condition */);

-- Indexes (jika perlu)
CREATE INDEX IF NOT EXISTS idx_xxx ON public.table_name (column_name);
```

**REFERENSI SCHEMA LENGKAP:** Lihat `implementation_plan.md` bagian "2. Skema Database & Tabel" untuk ERD dan semua kolom.

**PENTING untuk RLS:**
- Semua tabel yang menyimpan data tenant (umkm-specific) HARUS punya RLS
- Gunakan `auth.uid()` untuk verifikasi user ownership
- Pattern: user_id → umkm_profiles → child tables (transactions, dll)

#### Step 2.2 — Eloquent Models

**KONVENSI MODEL:**
```php
<?php
// app/Models/NamaModel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NamaModel extends Model
{
    use HasUuids;

    protected $table = 'table_name'; // Explicit table name
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        // list semua kolom yang boleh mass-assign
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // jsonb columns:
        'data_column' => 'array',
    ];

    // === RELATIONSHIPS ===

    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChildModel::class, 'this_id');
    }
}
```

**DAFTAR MODEL YANG HARUS DIBUAT:**

| Model | Table | Key Relationships |
|---|---|---|
| `User` | users | hasMany(UmkmProfile) |
| `UmkmProfile` | umkm_profiles | belongsTo(User), hasMany(Outlet, Transaction, ...) |
| `Outlet` | outlets | belongsTo(UmkmProfile), hasMany(Transaction) |
| `Category` | categories | belongsTo(UmkmProfile), hasMany(Transaction) |
| `Transaction` | transactions | belongsTo(UmkmProfile, Outlet, Category) |
| `Report` | reports | belongsTo(UmkmProfile) |
| `MarketAnalysis` | market_analyses | belongsTo(UmkmProfile), hasMany(Competitor) |
| `Competitor` | competitors | belongsTo(MarketAnalysis) |
| `Demographic` | demographics | belongsTo(UmkmProfile) |
| `ContentAsset` | content_assets | belongsTo(UmkmProfile), hasMany(PublishJob) |
| `PublishJob` | publish_jobs | belongsTo(ContentAsset) |
| `Invite` | invites | belongsTo(UmkmProfile) |
| `Product` | products | belongsTo(UmkmProfile) |

#### Step 2.3 — Supabase Auth Service

```php
<?php
// app/Services/Supabase/SupabaseAuthService.php

namespace App\Services\Supabase;

use Illuminate\Support\Facades\Http;

class SupabaseAuthService
{
    private string $url;
    private string $key;

    public function __construct()
    {
        $this->url = config('supabase.url');
        $this->key = config('supabase.key');
    }

    /**
     * Sign in dengan email + password
     */
    public function signInWithPassword(string $email, string $password): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=password", [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            throw new \Exception($response->json('error_description', 'Login gagal'));
        }

        return $response->json();
    }

    /**
     * Sign up dengan email + password
     */
    public function signUp(string $email, string $password, array $metadata = []): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/signup", [
            'email' => $email,
            'password' => $password,
            'data' => $metadata,
        ]);

        if ($response->failed()) {
            throw new \Exception($response->json('error_description', 'Registrasi gagal'));
        }

        return $response->json();
    }

    /**
     * Get user dari access token
     */
    public function getUser(string $accessToken): ?array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->get("{$this->url}/auth/v1/user");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Sign out
     */
    public function signOut(string $accessToken): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->post("{$this->url}/auth/v1/logout");
    }

    /**
     * Reset password
     */
    public function resetPassword(string $email): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);
    }

    /**
     * Google OAuth — get redirect URL
     */
    public function getGoogleOAuthUrl(string $redirectTo): string
    {
        return "{$this->url}/auth/v1/authorize?provider=google&redirect_to={$redirectTo}";
    }

    /**
     * Exchange code for session (setelah OAuth callback)
     */
    public function exchangeCodeForSession(string $code): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=pkce", [
            'auth_code' => $code,
        ]);

        return $response->json();
    }
}
```

#### Step 2.4 — Auth Middleware

```php
<?php
// app/Http/Middleware/SupabaseAuth.php

namespace App\Http\Middleware;

use App\Services\Supabase\SupabaseAuthService;
use Closure;
use Illuminate\Http\Request;

class SupabaseAuth
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function handle(Request $request, Closure $next)
    {
        $token = session('supabase_access_token');

        if (!$token) {
            return redirect()->route('login');
        }

        $user = $this->auth->getUser($token);

        if (!$user) {
            session()->forget(['supabase_access_token', 'supabase_refresh_token', 'supabase_user']);
            return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        // Share user data ke semua views
        view()->share('authUser', $user);
        $request->merge(['supabase_user' => $user]);

        return $next($request);
    }
}
```

Register middleware di `bootstrap/app.php` (Laravel 11+):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'auth:supabase' => \App\Http\Middleware\SupabaseAuth::class,
        'profile.complete' => \App\Http\Middleware\EnsureProfileComplete::class,
        'role' => \App\Http\Middleware\CheckRole::class,
    ]);
})
```

#### Step 2.5 — Login Controller

```php
<?php
// app/Http/Controllers/Auth/LoginController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'Email atau nomor telepon wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        try {
            $result = $this->auth->signInWithPassword($request->email, $request->password);

            session([
                'supabase_access_token' => $result['access_token'],
                'supabase_refresh_token' => $result['refresh_token'],
                'supabase_user' => $result['user'],
            ]);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => 'Email atau kata sandi salah. Silakan coba lagi.',
            ])->withInput(['email' => $request->email]);
        }
    }

    public function destroy()
    {
        $token = session('supabase_access_token');
        if ($token) {
            $this->auth->signOut($token);
        }

        session()->flush();
        return redirect()->route('login');
    }
}
```

---

### PHASE 3: Core UI Shell & Navigation

#### KONVENSI BLADE COMPONENT

Gunakan **anonymous Blade components** untuk UI elements:

```php
{{-- resources/views/components/ui/stat-card.blade.php --}}

@props([
    'title',
    'value',
    'icon' => null,
    'trend' => null,
    'trendLabel' => null,
    'variant' => 'default', // 'default' | 'success' | 'warning'
])

<div class="bg-surface border border-border rounded-lg p-6 shadow-card flex flex-col justify-between h-48">
    <div class="flex items-start justify-between">
        <div class="space-y-1">
            <p class="text-sm font-semibold text-on-surface-variant tracking-wide">{{ $title }}</p>
            <h3 class="text-2xl font-bold font-display text-on-surface">{{ $value }}</h3>
        </div>
        @if($icon)
        <div class="size-10 rounded-full bg-primary-light flex items-center justify-center">
            {!! $icon !!}
        </div>
        @endif
    </div>
    @if($trend)
    <div class="flex items-center gap-2 mt-auto">
        <span class="text-xs font-semibold text-success tracking-wide">{{ $trend }}</span>
        @if($trendLabel)
        <span class="text-xs font-semibold text-on-surface-variant tracking-wide">{{ $trendLabel }}</span>
        @endif
    </div>
    @endif
</div>
```

**Penggunaan:**
```blade
<x-ui.stat-card
    title="Penjualan Hari Ini"
    value="Rp 1.250.000"
    trend="↑ 15%"
    trendLabel="vs kemarin"
/>
```

#### LAYOUT TEMPLATE — app.blade.php

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SiJual' }} — MSME Command Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="font-body text-on-surface bg-background antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <x-navigation.side-nav-bar :active="$activeNav ?? ''" />

        {{-- Main area --}}
        <div class="flex-1 pl-64">
            {{-- Top App Bar --}}
            <x-navigation.top-app-bar />

            {{-- Page Content --}}
            <main class="pt-16 min-h-screen">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Copilot Bar (global) --}}
    @if(isset($showCopilot) && $showCopilot)
        <x-ui.copilot-bar />
    @endif

    {{-- Mobile Bottom Nav --}}
    <x-navigation.bottom-nav-bar :active="$activeNav ?? ''" class="lg:hidden" />

    @stack('scripts')
</body>
</html>
```

#### LAYOUT TEMPLATE — guest.blade.php

```blade
{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Masuk' }} — SiJual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body text-on-surface antialiased">
    {{ $slot }}
</body>
</html>
```

#### SIDE NAV BAR COMPONENT

```blade
{{-- resources/views/components/navigation/side-nav-bar.blade.php --}}
@props(['active' => ''])

<aside class="fixed left-0 top-0 bottom-0 w-64 bg-surface border-r border-border shadow-sm flex flex-col justify-between py-6 z-40 hidden lg:flex">
    {{-- Logo --}}
    <div>
        <div class="flex items-center gap-2 px-4 pb-8">
            <div class="size-10 bg-primary rounded-full flex items-center justify-center">
                {{-- Logo icon SVG --}}
                <svg class="size-5 text-white" fill="currentColor" viewBox="0 0 20 20"><!-- icon --></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold font-display text-primary">SiJual</h1>
                <p class="text-xs font-semibold text-on-surface-variant tracking-wide">MSME Command Center</p>
            </div>
        </div>

        {{-- New Transaction CTA --}}
        <div class="px-2 pb-2">
            <a href="{{ route('sikas.transactions.create') }}"
               class="flex items-center justify-center gap-2 w-full bg-primary text-white font-semibold text-sm py-2 px-4 rounded-md shadow-sm hover:bg-primary/90 transition">
                <svg class="size-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                New Transaction
            </a>
        </div>

        {{-- Nav Links --}}
        <nav class="flex flex-col gap-1 px-2 mt-2">
            <x-navigation.nav-link href="{{ route('dashboard') }}" :active="$active === 'hub'" icon="grid">
                SiJual Hub
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sikas.dashboard') }}" :active="$active === 'sikas'" icon="wallet">
                SiKas
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sipasar.landing') }}" :active="$active === 'sipasar'" icon="map">
                SiPasar
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sipromo.landing') }}" :active="$active === 'sipromo'" icon="megaphone">
                SiPromo
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sistok.products.index') }}" :active="$active === 'sistok'" icon="box">
                SiStok
            </x-navigation.nav-link>
        </nav>
    </div>

    {{-- Bottom Links --}}
    <div class="flex flex-col gap-1 px-2">
        <x-navigation.nav-link href="#" icon="settings">Settings</x-navigation.nav-link>
        <x-navigation.nav-link href="#" icon="help">Support</x-navigation.nav-link>
    </div>
</aside>
```

#### NAV LINK SUB-COMPONENT

```blade
{{-- resources/views/components/navigation/nav-link.blade.php --}}
@props(['active' => false, 'icon' => '', 'href' => '#'])

<a href="{{ $href }}"
   @class([
       'flex items-center gap-4 px-4 py-2 text-sm font-semibold tracking-wide rounded-md transition-colors',
       'bg-primary-subtle text-primary border-r-4 border-primary' => $active,
       'text-on-surface-variant hover:bg-surface-alt' => !$active,
   ])>
    {{-- Icon placeholder - ganti dengan SVG sesuai $icon --}}
    <span class="size-[18px]">
        @switch($icon)
            @case('grid')
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                @break
            @case('wallet')
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM14 13a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                @break
            @default
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/></svg>
        @endswitch
    </span>
    <span>{{ $slot }}</span>
</a>
```

---

### PHASE 4: SiKas Features

#### KONVENSI CONTROLLER

```php
<?php
// app/Http/Controllers/SiKas/TransactionController.php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Tampilkan semua transaksi (Transaction History page)
     */
    public function index(Request $request)
    {
        $umkm = $this->getActiveUmkm($request);

        $transactions = Transaction::where('umkm_id', $umkm->id)
            ->when($request->outlet_id, fn($q, $id) => $q->where('outlet_id', $id))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->category_id, fn($q, $id) => $q->where('category_id', $id))
            ->when($request->search, fn($q, $search) => $q->where('description', 'ilike', "%{$search}%"))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('transaction_date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('transaction_date', '<=', $date))
            ->with(['category', 'outlet'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('sikas.transactions', [
            'transactions' => $transactions,
            'activeNav' => 'sikas',
        ]);
    }

    /**
     * Simpan transaksi baru
     */
    public function store(TransactionRequest $request)
    {
        $umkm = $this->getActiveUmkm($request);

        $transaction = Transaction::create([
            'umkm_id' => $umkm->id,
            'outlet_id' => $request->outlet_id ?? $umkm->outlets()->where('is_primary', true)->value('id'),
            'category_id' => $request->category_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'source' => $request->source ?? 'manual',
            'payment_method' => $request->payment_method,
            'transaction_date' => $request->transaction_date ?? now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $transaction], 201);
        }

        return redirect()->route('sikas.transactions.index')
            ->with('success', 'Transaksi berhasil dicatat.');
    }

    /**
     * Helper: get active UMKM profile dari session user
     */
    private function getActiveUmkm(Request $request): UmkmProfile
    {
        $user = $request->supabase_user;
        return UmkmProfile::where('user_id', $user['id'])->firstOrFail();
    }
}
```

#### KONVENSI FORM REQUEST VALIDATION

```php
<?php
// app/Http/Requests/TransactionRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth sudah dihandle middleware
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:income,expense',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
            'payment_method' => 'nullable|string|max:50',
            'transaction_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'source' => 'nullable|in:voice,manual,qris',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'type.in' => 'Jenis transaksi harus Pemasukan atau Pengeluaran.',
            'amount.required' => 'Jumlah transaksi wajib diisi.',
            'amount.min' => 'Jumlah transaksi minimal Rp 1.',
            'description.required' => 'Deskripsi transaksi wajib diisi.',
        ];
    }
}
```

#### KONVENSI SERVICE CLASS (AI)

```php
<?php
// app/Services/AI/GeminiService.php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.gemini.api_key');
        $this->model = config('ai.gemini.model');
    }

    /**
     * Generate text content via Gemini
     */
    public function generateContent(string $prompt, array $options = []): string
    {
        try {
            $response = Http::timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => array_merge([
                        'temperature' => 0.7,
                        'maxOutputTokens' => 2048,
                    ], $options),
                ]);

            if ($response->failed()) {
                throw new \Exception("Gemini API error: " . $response->body());
            }

            return $response->json('candidates.0.content.parts.0.text', '');
        } catch (\Exception $e) {
            Log::warning("Gemini failed, falling back to OpenAI: {$e->getMessage()}");
            return app(OpenAIFallbackService::class)->generate($prompt, $options);
        }
    }

    /**
     * Categorize transaction dari text deskripsi
     */
    public function categorizeTransaction(string $text): array
    {
        $prompt = <<<PROMPT
Kamu adalah asisten keuangan UMKM Indonesia. Analisis teks transaksi berikut dan ekstrak informasi:

Teks: "{$text}"

Berikan output dalam format JSON:
{
    "type": "income" atau "expense",
    "amount": angka (tanpa titik/koma, dalam Rupiah),
    "description": "deskripsi singkat transaksi",
    "category": "nama kategori (e.g., Penjualan, Belanja Bahan, Operasional, dll.)",
    "payment_method": "metode bayar jika disebutkan (tunai, transfer, QRIS, dll.)",
    "confidence": angka 0-1 seberapa yakin kamu
}

PENTING: Output HANYA JSON, tanpa markdown code block atau penjelasan lain.
PROMPT;

        $result = $this->generateContent($prompt, ['temperature' => 0.3]);

        // Parse JSON dari response
        $cleaned = trim($result);
        $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        return json_decode($cleaned, true) ?? [
            'type' => 'expense',
            'amount' => 0,
            'description' => $text,
            'category' => 'Lainnya',
            'confidence' => 0,
        ];
    }
}
```

---

### PHASE 5: SiPasar Features

#### Mapbox Integration (Alpine.js)

```javascript
// resources/js/alpine/map.js

import mapboxgl from 'mapbox-gl';

export default function mapComponent() {
    return {
        map: null,
        markers: [],
        radiusCircle: null,

        init() {
            mapboxgl.accessToken = '{{ config("services.mapbox.token") }}';

            this.map = new mapboxgl.Map({
                container: this.$refs.mapContainer,
                style: 'mapbox://styles/mapbox/light-v11',
                center: [106.8456, -6.2088], // Default: Jakarta
                zoom: 13,
            });

            this.map.addControl(new mapboxgl.NavigationControl());
        },

        setCenter(lng, lat, radiusKm = 1.5) {
            this.map.flyTo({ center: [lng, lat], zoom: 14 });
            this.drawRadius(lng, lat, radiusKm);
        },

        drawRadius(lng, lat, radiusKm) {
            // Remove existing radius
            if (this.map.getSource('radius-circle')) {
                this.map.removeLayer('radius-fill');
                this.map.removeLayer('radius-outline');
                this.map.removeSource('radius-circle');
            }

            const circle = this.createGeoJSONCircle([lng, lat], radiusKm);

            this.map.addSource('radius-circle', {
                type: 'geojson',
                data: circle,
            });

            this.map.addLayer({
                id: 'radius-fill',
                type: 'fill',
                source: 'radius-circle',
                paint: {
                    'fill-color': '#9D3D2B',
                    'fill-opacity': 0.1,
                },
            });

            this.map.addLayer({
                id: 'radius-outline',
                type: 'line',
                source: 'radius-circle',
                paint: {
                    'line-color': '#9D3D2B',
                    'line-width': 2,
                    'line-dasharray': [2, 2],
                },
            });
        },

        addCompetitorPin(lng, lat, name, rating) {
            const marker = new mapboxgl.Marker({ color: '#506356' })
                .setLngLat([lng, lat])
                .setPopup(new mapboxgl.Popup().setHTML(`
                    <strong>${name}</strong><br>
                    Rating: ${rating}/5
                `))
                .addTo(this.map);

            this.markers.push(marker);
        },

        createGeoJSONCircle(center, radiusKm, points = 64) {
            const coords = [];
            const distanceX = radiusKm / (111.320 * Math.cos(center[1] * Math.PI / 180));
            const distanceY = radiusKm / 110.574;

            for (let i = 0; i < points; i++) {
                const theta = (i / points) * (2 * Math.PI);
                coords.push([
                    center[0] + distanceX * Math.cos(theta),
                    center[1] + distanceY * Math.sin(theta),
                ]);
            }
            coords.push(coords[0]);

            return {
                type: 'Feature',
                geometry: { type: 'Polygon', coordinates: [coords] },
            };
        },
    };
}
```

---

### PHASE 6: SiPromo Features

#### Content Generation Flow

```
User Input → Content Brief Form
    ↓
Laravel Controller (POST /sipromo/generate)
    ↓
PromptOptimizationService.optimize(userPrompt, contentType, tone)
    ↓
GeminiService.generateContent(optimizedPrompt) → generated text
    ↓
FluxSchnellService.generateImage(imagePrompt) → image URL
    ↓
CaptionGeneratorService.generate(text, product) → caption + hashtags
    ↓
BrandGuardService.check(content, brandProfile) → pass/fail + suggestions
    ↓
Save ContentAsset to DB + Upload image to Supabase Storage
    ↓
Redirect to Preview page (GET /sipromo/preview/{id})
```

---

## 🎨 KONVENSI UI & STYLING

### Warna — Cheat Sheet Tailwind Classes

| Kebutuhan | Class |
|---|---|
| Background halaman | `bg-background` |
| Background card/sidebar | `bg-surface` |
| Background input/search | `bg-surface-alt` |
| Text heading | `text-on-surface` |
| Text body/deskripsi | `text-on-surface-variant` |
| Text link/accent | `text-primary` |
| Button primary | `bg-primary text-white` |
| Button hover | `hover:bg-primary/90` |
| Border card | `border border-border` |
| Border input | `border border-border-input` |
| Income (positive) | `text-success` |
| Expense (negative) | `text-on-surface` |
| Error/alert | `text-error` |
| Badge/tag muted | `bg-muted text-on-surface-variant` |
| Active nav item | `bg-primary-subtle text-primary border-r-4 border-primary` |

### Typography — Cheat Sheet

| Kebutuhan | Class |
|---|---|
| Hero/page heading (H1) | `font-display font-bold text-5xl` → 48px |
| Section heading (H2) | `font-display font-bold text-3xl` → 32px |
| Card heading (H3) | `font-display font-bold text-2xl` → 24px |
| Subheading | `font-body font-semibold text-xl` → 20px |
| Body text | `font-body text-base` → 16px |
| Body large | `font-body text-lg` → 18px |
| Label/semibold | `font-body font-semibold text-sm tracking-wide` → 14px |
| Caption/small | `font-body font-semibold text-xs tracking-wider` → 12px |

### Spacing — Cheat Sheet

| Kebutuhan | Class |
|---|---|
| Page padding | `px-10 py-10` (40px) |
| Section gap | `gap-8` (32px) |
| Card gap | `gap-6` (24px) |
| Card padding | `p-6` (24px) |
| Inner element gap | `gap-4` (16px) |
| Small gap | `gap-2` (8px) |
| Tiny gap | `gap-1` (4px) |

### Card Pattern

```blade
<div class="bg-surface border border-border rounded-lg shadow-elevated p-6">
    {{-- Card content --}}
</div>
```

### Glassmorphism Pattern (untuk special elements)

```blade
<div class="backdrop-blur-sm bg-white/10 border border-white/20 rounded-lg shadow-hero">
    {{-- Glass content --}}
</div>
```

---

## 🧪 KONVENSI TESTING

### Unit Test Pattern

```php
<?php
// tests/Unit/Services/TransactionCategorizerTest.php

namespace Tests\Unit\Services;

use App\Services\AI\GeminiService;
use Tests\TestCase;

class TransactionCategorizerTest extends TestCase
{
    public function test_categorize_income_transaction(): void
    {
        $service = app(GeminiService::class);

        $result = $service->categorizeTransaction('Jual beras 5kg tiga bungkus, total tujuh puluh ribu');

        $this->assertEquals('income', $result['type']);
        $this->assertGreaterThan(0, $result['amount']);
        $this->assertNotEmpty($result['description']);
    }

    public function test_categorize_expense_transaction(): void
    {
        $service = app(GeminiService::class);

        $result = $service->categorizeTransaction('Beli minyak goreng 2 liter, habis tiga puluh lima ribu dari dompet');

        $this->assertEquals('expense', $result['type']);
        $this->assertEquals(35000, $result['amount']);
    }
}
```

### Feature Test Pattern

```php
<?php
// tests/Feature/Auth/LoginTest.php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Selamat Datang di SiJual');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->post('/login', []);
        $response->assertSessionHasErrors(['email', 'password']);
    }
}
```

---

## 📦 KONVENSI FORMAT DATA

### Currency Formatting (Rupiah)

```php
// Selalu gunakan helper ini untuk menampilkan Rupiah
function formatRupiah(int $amount, bool $showSign = false): string
{
    $formatted = 'Rp ' . number_format(abs($amount), 0, ',', '.');

    if ($showSign) {
        return $amount >= 0 ? "+ {$formatted}" : "- {$formatted}";
    }

    return $formatted;
}
```

```javascript
// Client-side (resources/js/utils/format.js)
export function formatRupiah(amount, showSign = false) {
    const abs = Math.abs(amount);
    const formatted = 'Rp ' + abs.toLocaleString('id-ID');

    if (showSign) {
        return amount >= 0 ? `+ ${formatted}` : `- ${formatted}`;
    }

    return formatted;
}
```

### Date Formatting (Bahasa Indonesia)

```php
// Gunakan Carbon dengan locale 'id'
use Carbon\Carbon;

Carbon::setLocale('id');
$date = Carbon::parse($transaction->created_at);

// "Hari ini, 09:41"
// "Kemarin, 14:20"
// "24 Okt 2025"
```

---

## 🔍 CHECKLIST KUALITAS PER-FILE

Sebelum menganggap sebuah file selesai, pastikan:

### Blade View
- [ ] Menggunakan layout yang benar (`<x-layouts.app>` atau `<x-layouts.guest>`)
- [ ] `$activeNav` di-pass ke layout (untuk sidebar highlight)
- [ ] Responsive: tampil baik di 375px, 768px, 1280px
- [ ] Semua text dalam Bahasa Indonesia
- [ ] Loading state ada (skeleton / spinner)
- [ ] Empty state ada (ikon + pesan "Belum ada data")
- [ ] Error state ada (pesan error + tombol retry)
- [ ] Semua form punya CSRF token (`@csrf`)
- [ ] Semua link pakai `{{ route('name') }}` bukan hardcode URL

### Controller
- [ ] Namespace benar
- [ ] Menggunakan Form Request untuk validation
- [ ] Menghandle JSON response untuk API calls (`$request->wantsJson()`)
- [ ] Error handling dengan try-catch untuk external services
- [ ] Mengembalikan proper HTTP status codes

### Model
- [ ] `use HasUuids` trait
- [ ] `$table` property set explicitly
- [ ] `$fillable` lengkap
- [ ] `$casts` untuk datetime & jsonb columns
- [ ] Relationships defined

### Service
- [ ] Constructor inject dependencies via config
- [ ] Logging untuk failures
- [ ] Timeout set untuk external HTTP calls
- [ ] Fallback mechanism untuk AI services

---

## 🛑 COMMON MISTAKES — HINDARI

| ❌ Jangan | ✅ Lakukan |
|---|---|
| `Route::get('/sikas/dashboard', ...)` | `Route::get('/sikas', ...)->name('sikas.dashboard')` |
| `<a href="/sikas">` | `<a href="{{ route('sikas.dashboard') }}">` |
| `color: #9D3D2B;` di inline style | `text-primary` Tailwind class |
| `$request->input('email')` tanpa validasi | Gunakan `FormRequest` class |
| `DB::table('users')->...` | Gunakan Eloquent Model: `User::where(...)` |
| `json_encode()` manual response | `return response()->json([...])` |
| Console.log di production JS | Hapus semua `console.log` |
| Hardcode API key di kode | Gunakan `config('ai.gemini.api_key')` |
| `<form>` tanpa `@csrf` | Selalu tambah `@csrf` (dan `@method('PUT')` jika perlu) |
| Font generic (`sans-serif`) | `font-body` atau `font-display` dari design tokens |

---

## 📁 FILE REFERENSI

| File | Isi | Kapan Dibaca |
|---|---|---|
| `summary.md` | Ringkasan proyek, design tokens, component list | Sebelum mengerjakan task apapun |
| `implementation_plan.md` | ERD, folder structure, routing, Figma mapping | Sebelum membuat file baru |
| `tasks.md` | Checklist to-do & status | Sebelum dan sesudah setiap task |
| `sijual_prd_v2/` | PRD detail per-ticket | Jika perlu memahami requirement spesifik |

---

## 🔄 WORKFLOW EKSEKUSI TASK

```
1. Baca task dari tasks.md
2. Baca referensi terkait (implementation_plan.md, summary.md)
3. Jika ada Figma node reference → gunakan MCP Figma untuk lihat desain
4. Jika perlu syntax reference → gunakan Context7 MCP
5. Buat file/edit kode
6. Jalankan verifikasi:
   - `php artisan route:list` (routes benar?)
   - `npm run build` (no build errors?)
   - `php artisan serve` (no runtime errors?)
   - Buka browser → cek halaman
7. Update tasks.md ([ ] → [x])
8. Lanjut ke task berikutnya
```

---

## 🧭 URUTAN PENGERJAAN OPTIMAL

Ikuti urutan ini untuk menghindari dependency issues:

```
Phase 1 (Setup)
  └→ Step 1.1 Laravel init
  └→ Step 1.2 Tailwind + Vite
  └→ Step 1.3 Fonts
  └→ Step 1.4 Supabase connection
  └→ Step 1.5 Alpine.js
  └→ Step 1.6 Verify

Phase 2 (DB + Auth)
  └→ Step 2.1 SQL migrations → jalankan di Supabase
  └→ Step 2.2 Eloquent Models
  └→ Step 2.3 SupabaseAuthService
  └→ Step 2.4 Auth middleware
  └→ Step 2.5 Login/Register controllers
  └→ Step 2.6 Auth Blade views (login, register)
  └→ Step 2.7 Profile & Onboarding

Phase 3 (UI Shell)
  └→ Step 3.1 Layout templates (app.blade.php, guest.blade.php)
  └→ Step 3.2 SideNavBar component
  └→ Step 3.3 TopAppBar component
  └→ Step 3.4 BottomNavBar component (mobile)
  └→ Step 3.5 Shared UI components (stat-card, alert-card, etc.)
  └→ Step 3.6 Hub Dashboard page
  └→ Step 3.7 Routes setup (web.php)

Phase 4 (SiKas)
  └→ Step 4.1 Transaction model + migrations
  └→ Step 4.2 TransactionController (CRUD)
  └→ Step 4.3 SiKas Dashboard view
  └→ Step 4.4 Transaction History view
  └→ Step 4.5 Voice Input (Alpine.js + Whisper service)
  └→ Step 4.6 AI Categorization service
  └→ Step 4.7 Reports + Charts
  └→ Step 4.8 Export PDF/CSV

Phase 5 (SiPasar)
  └→ Step 5.1 MarketAnalysis + Competitor models
  └→ Step 5.2 Mapbox integration (Alpine.js)
  └→ Step 5.3 SiPasar landing + analysis views
  └→ Step 5.4 Competitor scraper service
  └→ Step 5.5 BPS data service
  └→ Step 5.6 Market Fit Score AI service

Phase 6 (SiPromo)
  └→ Step 6.1 ContentAsset + PublishJob models
  └→ Step 6.2 Content generation flow (Gemini + Flux)
  └→ Step 6.3 SiPromo landing + generate + preview views
  └→ Step 6.4 Meta OAuth + publish flow
  └→ Step 6.5 Copilot Bar (global)

Phase 7 (SiStok)
  └→ Step 7.1 Product model
  └→ Step 7.2 ProductController
  └→ Step 7.3 SiStok inventory table view

Phase 8 (Testing)
  └→ Unit tests
  └→ Feature tests
  └→ E2E tests (Playwright)

Phase 9 (Deploy)
  └→ Production config
  └→ Deploy
  └→ Documentation

Phase 10 (Polish)
  └→ Final QA
  └→ Bug fixes
  └→ Demo prep
```

---

## ⚡ TIPS PERFORMA

1. **Lazy load images** — Gunakan `loading="lazy"` pada semua `<img>` tags
2. **Pagination** — Jangan `->get()` semua data, selalu `->paginate(20)`
3. **Eager loading** — Gunakan `->with(['relation'])` untuk menghindari N+1 queries
4. **Cache** — Cache hasil analisis SiPasar (`Cache::remember()` dengan TTL)
5. **Queue** — Proses berat (scraping, AI generation, export PDF) harus masuk queue (`dispatch(new Job)`)
6. **Debounce** — Search input harus debounced (300ms) di Alpine.js

---

> **Dokumen ini adalah sumber kebenaran tertinggi untuk AI agent.**
> Jika ada konflik antara dokumen ini dan file lain, ikuti dokumen ini.
