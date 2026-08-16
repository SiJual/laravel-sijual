<?php

namespace App\Http\Controllers\SiPromo;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\UmkmProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

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
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $content = ContentAsset::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        return view('sipromo.preview', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'content' => $content,
        ]);
    }

    public function history(): View
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $contents = ContentAsset::where('umkm_id', $profile->id)->latest()->paginate(12);

        return view('sipromo.history', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'contents' => $contents,
        ]);
    }
}
