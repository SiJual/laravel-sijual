<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Services\AI\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceInputController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private \App\Services\AI\WhisperSTTService $whisper
    ) {}

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,webm,mp4,m4a,ogg',
            'text' => 'nullable|string|max:500',
        ]);

        $text = $request->input('text');

        if ($request->hasFile('audio')) {
            $transcribedText = $this->whisper->transcribe($request->file('audio'));
            if (!$transcribedText) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal memproses suara. Silakan coba lagi atau input manual.'
                ], 422);
            }
            $text = $transcribedText;
        }

        if (empty($text)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Teks atau rekaman suara tidak valid.'
            ], 422);
        }

        $categorized = $this->gemini->categorizeTransaction($text);

        return response()->json([
            'status' => 'success',
            'data' => $categorized,
            'transcribed_text' => $request->hasFile('audio') ? $text : null,
        ]);
    }
}
