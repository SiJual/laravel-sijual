<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'category_id' => 'nullable|uuid',
            'outlet_id' => 'nullable|uuid',
            'payment_method' => 'nullable|string|max:50',
            'transaction_date' => 'required|date',
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
