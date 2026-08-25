<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $txDate = $this->transaction_date;
        if (empty($txDate)) {
            $this->merge([
                'transaction_date' => now()->format('Y-m-d H:i:s'),
            ]);
        } else {
            $cleaned = str_replace('T', ' ', trim($txDate));
            if (strlen($cleaned) === 10) {
                $this->merge([
                    'transaction_date' => $cleaned . ' ' . now()->format('H:i:s'),
                ]);
            } elseif (strlen($cleaned) === 16) {
                $this->merge([
                    'transaction_date' => $cleaned . ':00',
                ]);
            } else {
                $this->merge([
                    'transaction_date' => $cleaned,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'category_id' => 'nullable|string',
            'product_id' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'category_name' => 'nullable|string|max:100',
            'outlet_id' => 'nullable|string',
            'payment_method' => 'nullable|string|max:50',
            'transaction_date' => 'nullable',
            'notes' => 'nullable|string',
            'source' => 'nullable|in:voice,manual,qris',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'amount.required' => 'Nominal transaksi wajib diisi.',
            'amount.numeric' => 'Nominal harus berupa angka.',
            'amount.min' => 'Nominal minimal Rp 1.',
            'description.required' => 'Deskripsi transaksi wajib diisi.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
        ];
    }
}
