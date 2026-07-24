<?php

namespace App\Services\Payment;

use App\Models\Transaction;

class QrisSyncService
{
    /**
     * Reconcile pending digital QRIS transactions.
     *
     * @param string $umkmId
     * @return array{synced_count: int, total_amount: int}
     */
    public function syncQrisTransactions(string $umkmId): array
    {
        // Mock QRIS sync reconciliation result
        return [
            'synced_count' => 0,
            'total_amount' => 0,
        ];
    }
}
