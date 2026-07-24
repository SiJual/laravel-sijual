<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\UmkmProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function onboarding(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->first();

        return view('onboarding.profile-setup', compact('profile'));
    }

    public function completeOnboarding(Request $request): RedirectResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
        ], [
            'business_name.required' => 'Nama usaha wajib diisi.',
            'business_type.required' => 'Jenis usaha wajib dipilih.',
            'address.required' => 'Alamat usaha wajib diisi.',
            'city.required' => 'Kota/Kabupaten wajib diisi.',
            'province.required' => 'Provinsi wajib diisi.',
            'phone.required' => 'Nomor telepon/WhatsApp wajib diisi.',
        ]);

        $userSession = session('supabase_user');

        $profile = UmkmProfile::updateOrCreate(
            ['user_id' => $userSession['id']],
            [
                'business_name' => strip_tags($request->business_name),
                'business_type' => $request->business_type,
                'address' => strip_tags($request->address),
                'city' => $request->city,
                'province' => $request->province,
                'phone' => $request->phone,
                'profile_completeness' => 100,
            ]
        );

        // Ensure at least 1 primary outlet exists
        Outlet::firstOrCreate(
            ['umkm_id' => $profile->id, 'is_primary' => true],
            [
                'name' => strip_tags($request->business_name) . ' (Pusat)',
                'address' => strip_tags($request->address),
                'is_primary' => true,
            ]
        );

        return redirect()->route('dashboard')->with('success', 'Profil usaha Anda berhasil disimpan!');
    }

    public function edit(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        return view('profile.edit', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
        ]);

        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $sanitizedData = $request->only([
            'business_type',
            'city',
            'province',
            'phone',
        ]);
        $sanitizedData['business_name'] = strip_tags($request->business_name);
        $sanitizedData['address'] = strip_tags($request->address);

        $profile->update($sanitizedData);

        return back()->with('success', 'Profil usaha berhasil diperbarui.');
    }
}
