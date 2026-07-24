<x-layouts.app title="Pengaturan Profil — SiJual" activeNav="profile">
    <div class="p-6 lg:p-8 space-y-6 max-w-2xl">
        <h1 class="text-2xl font-bold font-display text-on-surface">Pengaturan Profil Usaha</h1>

        @if (session('success'))
            <div class="p-4 rounded-md bg-success-bg text-success text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('profile.update') }}" method="POST" class="bg-surface p-6 rounded-lg border border-border space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="business_name" class="block text-xs font-semibold text-on-surface mb-1">Nama Usaha</label>
                <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $profile->business_name) }}" required
                       class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
            </div>

            <div>
                <label for="business_type" class="block text-xs font-semibold text-on-surface mb-1">Jenis Usaha</label>
                <input type="text" id="business_type" name="business_type" value="{{ old('business_type', $profile->business_type) }}" required
                       class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="city" class="block text-xs font-semibold text-on-surface mb-1">Kota</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $profile->city) }}" required
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label for="province" class="block text-xs font-semibold text-on-surface mb-1">Provinsi</label>
                    <input type="text" id="province" name="province" value="{{ old('province', $profile->province) }}" required
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
            </div>

            <div>
                <label for="address" class="block text-xs font-semibold text-on-surface mb-1">Alamat</label>
                <textarea id="address" name="address" rows="2" required
                          class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">{{ old('address', $profile->address) }}</textarea>
            </div>

            <div>
                <label for="phone" class="block text-xs font-semibold text-on-surface mb-1">Telepon</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $profile->phone) }}" required
                       class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
            </div>

            <button type="submit" class="py-2.5 px-4 bg-primary text-white text-sm font-semibold rounded-md hover:bg-primary/90 transition">
                Simpan Perubahan
            </button>
        </form>
    </div>
</x-layouts.app>
