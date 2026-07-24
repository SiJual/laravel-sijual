<x-layouts.guest title="Daftar Akun — SiJual">
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-6 bg-background font-body">
        <div class="w-full max-w-md bg-surface p-8 sm:p-10 rounded-xl border border-border shadow-card">
            {{-- Logo & Brand --}}
            <div class="flex items-center gap-3 mb-8">
                <div class="size-10 bg-primary rounded-full flex items-center justify-center text-white shadow-sm">
                    <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/>
                    </svg>
                </div>
                <span class="text-2xl font-bold font-display text-primary tracking-tight">SiJual</span>
            </div>

            <div class="mb-6">
                <h1 class="text-2xl font-bold font-display text-on-surface mb-1">Registrasi Akun Baru</h1>
                <p class="text-sm font-medium text-on-surface-variant">Mulai kelola UMKM Anda lebih cerdas.</p>
            </div>

            <form action="{{ route('register') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="full_name" class="block text-xs font-semibold text-on-surface mb-1.5">Nama Lengkap Pemilik</label>
                    <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required autofocus
                           placeholder="Contoh: Budi Santoso"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                    @error('full_name')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-on-surface mb-1.5">Alamat Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                           placeholder="nama@tokoumkm.com"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                    @error('email')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-on-surface mb-1.5">Kata Sandi</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Minimal 6 karakter"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                    @error('password')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-on-surface mb-1.5">Konfirmasi Kata Sandi</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           placeholder="Ulangi kata sandi"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-primary text-white font-semibold text-sm rounded-md shadow-card hover:bg-primary/90 transition mt-2">
                    Daftar Sekarang
                </button>
            </form>

            <div class="mt-6 text-center text-xs font-semibold text-on-surface-variant">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="text-primary hover:underline">Masuk di sini</a>
            </div>
        </div>
    </div>
</x-layouts.guest>
