<?php

namespace App\Http\Controllers\SiPromo;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\UmkmProfile;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $recentGenerations = ContentAsset::where('umkm_id', $profile->id)
            ->latest()
            ->take(6)
            ->get();

        return view('sipromo.landing', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'recentGenerations' => $recentGenerations,
        ]);
    }

    public function preview(string $id): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $content = ContentAsset::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        return view('sipromo.preview', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'content' => $content,
        ]);
    }

    public function history(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $contents = ContentAsset::where('umkm_id', $profile->id)->latest()->paginate(12);

        return view('sipromo.history', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'contents' => $contents,
        ]);
    }
}
