<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\UmkmProfile;
use App\Services\Payment\QrisSyncService;
use Illuminate\Http\JsonResponse;

class QrisSyncController extends Controller
{
    public function __construct(private QrisSyncService $qrisService) {}

    public function sync(): JsonResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $result = $this->qrisService->syncQrisTransactions($profile->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Rekonsiliasi QRIS selesai.',
            'data' => $result,
        ]);
    }
}
