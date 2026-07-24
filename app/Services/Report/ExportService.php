<?php

namespace App\Services\Report;

use App\Models\Transaction;

class ExportService
{
    /**
     * Export transactions as CSV string.
     *
     * @param string $umkmId
     * @return string
     */
    public function exportCsv(string $umkmId): string
    {
        $transactions = Transaction::where('umkm_id', $umkmId)
            ->with(['category', 'outlet'])
            ->latest('transaction_date')
            ->get();

        $output = "ID,Tanggal,Jenis,Kategori,Deskripsi,Nominal (Rp),Metode Pembayaran,Outlet\n";

        foreach ($transactions as $tx) {
            $cat = str_replace(',', ' ', $tx->category->name ?? 'Lain-lain');
            $desc = str_replace(',', ' ', $tx->description ?? '-');
            $outlet = str_replace(',', ' ', $tx->outlet->name ?? 'Pusat');
            $output .= "{$tx->id},{$tx->transaction_date->format('Y-m-d')},{$tx->type},{$cat},{$desc},{$tx->amount},{$tx->payment_method},{$outlet}\n";
        }

        return $output;
    }
}
