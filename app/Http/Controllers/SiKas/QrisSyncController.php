<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\UmkmProfile;
use App\Services\Payment\QrisSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class QrisSyncController extends Controller
{
    public function __construct(private QrisSyncService $qrisService) {}

    public function sync(): JsonResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $result = $this->qrisService->syncQrisTransactions($profile->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekonsiliasi QRIS selesai.',
            'data' => $result,
        ]);
    }
}
