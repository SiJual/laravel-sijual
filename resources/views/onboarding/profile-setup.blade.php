<x-layouts.guest title="Lengkapi Profil Usaha — SiJual">
    <div class="min-h-screen flex items-center justify-center p-4 sm:p-6 bg-background font-body">
        <div class="w-full max-w-lg bg-surface p-8 sm:p-10 rounded-xl border border-border shadow-card">
            {{-- Header --}}
            <div class="flex items-center gap-3 mb-6">
                <div class="size-10 bg-primary rounded-full flex items-center justify-center text-white shadow-sm">
                    <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold font-display text-primary">SiJual Onboarding</h1>
                    <p class="text-xs font-semibold text-on-surface-variant">Langkah 1 dari 1: Informasi Usaha</p>
                </div>
            </div>

            @if (session('warning'))
                <div class="mb-4 p-3 rounded-md bg-primary-light text-primary text-xs font-semibold">
                    {{ session('warning') }}
                </div>
            @endif

            <form action="{{ route('onboarding') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="business_name" class="block text-xs font-semibold text-on-surface mb-1">Nama Usaha / Toko</label>
                    <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $profile->business_name ?? '') }}" required
                           placeholder="Contoh: Kopi Nusantara Kebayoran"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                    @error('business_name')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="business_type" class="block text-xs font-semibold text-on-surface mb-1">Kategori / Jenis Usaha</label>
                    <select id="business_type" name="business_type" required
                            class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                        <option value="">-- Pilih Jenis Usaha --</option>
                        <option value="Kuliner / F&B" {{ old('business_type', $profile->business_type ?? '') === 'Kuliner / F&B' ? 'selected' : '' }}>Kuliner / Food & Beverage</option>
                        <option value="Fashion & Kerajinan Batik" {{ old('business_type', $profile->business_type ?? '') === 'Fashion & Kerajinan Batik' ? 'selected' : '' }}>Fashion & Kerajinan Batik</option>
                        <option value="Toko Kelontong & Retail" {{ old('business_type', $profile->business_type ?? '') === 'Toko Kelontong & Retail' ? 'selected' : '' }}>Toko Kelontong & Retail</option>
                        <option value="Jasa & Perbaikan" {{ old('business_type', $profile->business_type ?? '') === 'Jasa & Perbaikan' ? 'selected' : '' }}>Jasa & Perbaikan</option>
                        <option value="Lainnya" {{ old('business_type', $profile->business_type ?? '') === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                    @error('business_type')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="city" class="block text-xs font-semibold text-on-surface mb-1">Kota / Kabupaten</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $profile->city ?? '') }}" required
                               placeholder="Jakarta Selatan"
                               class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                        @error('city')
                            <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="province" class="block text-xs font-semibold text-on-surface mb-1">Provinsi</label>
                        <input type="text" id="province" name="province" value="{{ old('province', $profile->province ?? '') }}" required
                               placeholder="DKI Jakarta"
                               class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                        @error('province')
                            <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-xs font-semibold text-on-surface mb-1">Alamat Lengkap</label>
                    <textarea id="address" name="address" rows="2" required
                              placeholder="Jl. Kyai Maja No. 12, Kebayoran Baru"
                              class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">{{ old('address', $profile->address ?? '') }}</textarea>
                    @error('address')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-xs font-semibold text-on-surface mb-1">Nomor Telepon / WhatsApp</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $profile->phone ?? '') }}" required
                           placeholder="081234567890"
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                    @error('phone')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 bg-primary text-white font-semibold text-sm rounded-md shadow-card hover:bg-primary/90 transition mt-4">
                    Simpan Profil & Masuk Dashboard →
                </button>
            </form>
        </div>
    </div>
</x-layouts.guest>
