<?php

namespace App\Http\Controllers\Copilot;

use App\Http\Controllers\Controller;
use App\Services\AI\CopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CopilotController extends Controller
{
    public function __construct(private CopilotService $copilot) {}

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $answer = $this->copilot->ask($request->question);

        return response()->json([
            'status' => 'success',
            'answer' => $answer,
        ]);
    }
}
