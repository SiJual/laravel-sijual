<?php

namespace App\Http\Controllers\SiPromo;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\UmkmProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $recentGenerations = ContentAsset::where('umkm_id', $profile->id)
            ->latest()
            ->take(6)
            ->get();

        // The pipeline grounds copy on real catalogue rows, so the form needs
        // the product list.
        $products = \App\Models\Product::where('umkm_id', $profile->id)->orderBy('name')->get();

        return view('sipromo.landing', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'recentGenerations' => $recentGenerations,
            'products' => $products,
        ]);
    }

    public function preview(Request $request, string $id): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $content = ContentAsset::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        return view('sipromo.preview', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'content' => $content,
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $content = ContentAsset::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        DB::transaction(function () use ($content) {
            // generation_runs restricts deletion of its content_asset_id (no
            // cascade), so it has to go first. publish_jobs/content_sources/
            // content_revisions/content_approvals cascade automatically.
            DB::table('generation_runs')->where('content_asset_id', $content->id)->delete();
            $content->delete();
        });

        return redirect()->route('sipromo.landing')->with('success', 'Konten promosi berhasil dihapus.');
    }

    public function history(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $contents = ContentAsset::where('umkm_id', $profile->id)->latest()->paginate(12);

        return view('sipromo.history', [
            'activeNav' => 'sipromo',
            'profile' => $profile,
            'contents' => $contents,
        ]);
    }
}
