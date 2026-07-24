<?php

namespace App\Services\AI;

class FinancialInsightService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Generate financial advice / insights based on transaction aggregates.
     *
     * @param int $totalIncome
     * @param int $totalExpense
     * @param array $topCategories
     * @return string
     */
    public function generateInsight(int $totalIncome, int $totalExpense, array $topCategories = []): string
    {
        $net = $totalIncome - $totalExpense;
        $prompt = <<<PROMPT
Anda adalah penasihat keuangan pintar SiKas untuk UMKM Indonesia.
Analisis data berikut dan berikan 2 kalimat rekomendasi bisnis yang praktis, suportif, dan ramah:
- Total Pemasukan: Rp {$totalIncome}
- Total Pengeluaran: Rp {$totalExpense}
- Saldo Bersih: Rp {$net}

Tulis dalam Bahasa Indonesia santun dan langsung pada inti.
PROMPT;

        try {
            return $this->gemini->generateContent($prompt);
        } catch (\Throwable $e) {
            if ($net > 0) {
                return "Arus kas usaha Anda dalam kondisi sehat dengan surplus Rp " . number_format($net, 0, ',', '.') . ". Pertimbangkan untuk mengalokasikan sebagian keuntungan ke tabungan cadangan usaha.";
            }
            return "Pengeluaran Anda saat ini melebihi atau mendekati pendapatan. Tinjau kembali pengeluaran operasional terbesar Anda untuk menjaga ketersediaan kas.";
        }
    }
}
