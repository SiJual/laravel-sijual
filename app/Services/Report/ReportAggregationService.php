<?php

namespace App\Services\Report;

use App\Models\Transaction;
use Carbon\Carbon;

class ReportAggregationService
{
    /**
     * Compute transaction aggregates for a given period.
     *
     * @param string $umkmId
     * @param string $periodType 'daily' | 'weekly' | 'monthly'
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array{total_income: int, total_expense: int, net_profit: int, transaction_count: int, category_breakdown: array}
     */
    public function aggregate(string $umkmId, string $periodType = 'monthly', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = Transaction::where('umkm_id', $umkmId);

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($periodType === 'daily') {
            $query->whereDate('transaction_date', Carbon::today()->toDateString());
        } elseif ($periodType === 'weekly') {
            $query->whereBetween('transaction_date', [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->endOfWeek()->toDateString()]);
        } else {
            $query->whereMonth('transaction_date', Carbon::now()->month)
                  ->whereYear('transaction_date', Carbon::now()->year);
        }

        $transactions = $query->with('category')->get();

        $income = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');

        $breakdown = [];
        foreach ($transactions as $tx) {
            $catName = $tx->category->name ?? 'Lain-lain';
            if (!isset($breakdown[$catName])) {
                $breakdown[$catName] = 0;
            }
            $breakdown[$catName] += $tx->amount;
        }

        return [
            'total_income' => (int) $income,
            'total_expense' => (int) $expense,
            'net_profit' => (int) ($income - $expense),
            'transaction_count' => $transactions->count(),
            'category_breakdown' => $breakdown,
        ];
    }
}
