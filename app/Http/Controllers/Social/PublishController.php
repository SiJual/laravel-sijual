<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\PublishJob;
use App\Services\Social\PublishSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    public function __construct(private PublishSchedulerService $scheduler) {}

    /**
     * Schedule a generated content for publishing.
     */
    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'content_id' => 'required|integer',
            'platform' => 'required|string|in:instagram,facebook',
            'scheduled_at' => 'required|date|after_or_equal:now',
        ]);

        try {
            $scheduledTime = Carbon::parse($request->scheduled_at, 'Asia/Jakarta')->setTimezone('UTC');
            
            $job = $this->scheduler->schedule(
                $request->content_id,
                $request->platform,
                $scheduledTime
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Konten berhasil dijadwalkan.',
                'data' => $job,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menjadwalkan konten: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of scheduled jobs for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $userSession = session('supabase_user');
        $profile = \App\Models\UmkmProfile::where('user_id', $userSession['id'])->first();

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'Profil UMKM tidak ditemukan'], 404);
        }

        $jobs = PublishJob::with('contentAsset')
                          ->whereHas('contentAsset', fn($q) => $q->where('umkm_id', $profile->id))
                          ->orderBy('scheduled_at', 'asc')
                          ->get();

        return response()->json([
            'status' => 'success',
            'data' => $jobs,
        ]);
    }
}
