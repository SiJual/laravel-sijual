<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhisperSTTService
{
    /**
     * Transcribe an audio file using OpenAI Whisper API.
     *
     * @param UploadedFile $audioFile
     * @return string|null The transcribed text, or null if it fails.
     */
    public function transcribe(UploadedFile $audioFile): ?string
    {
        $apiKey = !empty(config('ai.whisper.api_key')) ? config('ai.whisper.api_key') : config('ai.openai.api_key');

        if (empty($apiKey)) {
            Log::error('WhisperSTTService: OPENAI_API_KEY is not set.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])
            ->timeout(30)
            ->attach(
                'file', file_get_contents($audioFile->getRealPath()), $audioFile->getClientOriginalName()
            )
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'id', // Default to Indonesian for SiJual
            ]);

            if ($response->successful()) {
                return $response->json('text');
            }

            Log::error('Whisper API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('WhisperSTTService Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }
}
