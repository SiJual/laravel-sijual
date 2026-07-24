<?php

namespace App\Services\AI;

class CopilotService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Ask Copilot AI assistant across modules.
     *
     * @param string $question
     * @param array $contextData
     * @return string
     */
    public function ask(string $question, array $contextData = []): string
    {
        $prompt = <<<PROMPT
Anda adalah Copilot SiJual — Asisten Komando UMKM Indonesia.
Pertanyaan User: "{$question}"

Jawablah dengan ringkas, suportif, dan memberikan langkah aksi konkret untuk bisnis UMKM.
PROMPT;

        try {
            return $this->gemini->generateContent($prompt);
        } catch (\Throwable $e) {
            return "Saya SiJual Copilot. Untuk meningkatkan penjualan hari ini, pastikan Anda mencatat transaksi harian di SiKas dan memanfaatkan SiPromo untuk membuat iklan promosi otomatis!";
        }
    }
}
