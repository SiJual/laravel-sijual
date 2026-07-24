<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\UmkmProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        Outlet::create([
            'umkm_id' => $profile->id,
            'name' => $request->name,
            'address' => $request->address,
            'is_primary' => false,
        ]);

        return back()->with('success', 'Cabang outlet berhasil ditambahkan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $outlet = Outlet::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        if ($outlet->is_primary) {
            return back()->with('error', 'Outlet utama tidak dapat dihapus.');
        }

        $outlet->delete();
        return back()->with('success', 'Outlet berhasil dihapus.');
    }
}
