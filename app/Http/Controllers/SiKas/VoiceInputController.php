<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Services\AI\GeminiService;
use App\Services\AI\WhisperSTTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceInputController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private WhisperSTTService $whisper
    ) {}

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'nullable|file|max:20480',
            'text' => 'nullable|string|max:1000',
        ]);

        $text = $request->input('text');
        $transcribedText = null;
        $categorized = null;

        if ($request->hasFile('audio')) {
            $audioFile = $request->file('audio');

            // 1. Primary: Transcribe using OpenAI Whisper STT
            try {
                $transcribedText = $this->whisper->transcribe($audioFile);
                if (!empty($transcribedText)) {
                    $text = $transcribedText;
                }
            } catch (\Throwable $whisperError) {
                Log::warning('Whisper STT failed, trying Gemini multimodal audio: ' . $whisperError->getMessage());
            }

            // 2. Fallback: If Whisper didn't return text, try Gemini 2.5 Flash direct audio
            if (empty($text)) {
                try {
                    $categorized = $this->gemini->transcribeAndCategorizeAudio($audioFile);
                    $transcribedText = $categorized['transcribed_text'] ?? null;
                } catch (\Throwable $geminiError) {
                    Log::error('Both Whisper and Gemini audio processing failed: ' . $geminiError->getMessage());
                }
            }

            if ($audioFile && file_exists($audioFile->getRealPath())) {
                @unlink($audioFile->getRealPath());
            }
        }

        // If not already categorized via direct audio and we have text
        if (!$categorized && !empty($text)) {
            try {
                $categorized = $this->gemini->categorizeTransaction($text);
            } catch (\Throwable $e) {
                Log::error('Gemini text categorization failed: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menganalisis transaksi: ' . $e->getMessage()
                ], 422);
            }
        }

        if (!$categorized) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses suara. Pastikan Anda berbicara dengan jelas atau coba lagi.'
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => $categorized,
            'transcribed_text' => $transcribedText ?? $text,
        ]);
    }
}
