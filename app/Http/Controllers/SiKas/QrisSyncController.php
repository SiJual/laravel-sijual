<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\UmkmProfile;
use App\Services\Payment\QrisSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrisSyncController extends Controller
{
    public function __construct(private QrisSyncService $qrisService) {}

    public function sync(Request $request): JsonResponse|RedirectResponse
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $result = $this->qrisService->syncQrisTransactions($profile->id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Rekonsiliasi QRIS selesai.',
                'data' => $result,
            ]);
        }

        $formattedTotal = number_format($result['total_amount'], 0, ',', '.');
        return back()->with('success', "Berhasil menarik {$result['synced_count']} transaksi QRIS settlement (Total: Rp {$formattedTotal}).");
    }
}
