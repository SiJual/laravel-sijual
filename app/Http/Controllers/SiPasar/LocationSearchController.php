<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Services\Market\LocationSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    public function __construct(private LocationSearchService $service) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:3|max:255',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->service->search($request->query('q')),
        ]);
    }
}
