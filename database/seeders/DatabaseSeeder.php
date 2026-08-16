<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedDefaultCategories();
    }

    private function seedDefaultCategories(): void
    {
        $categories = [
            ['name' => 'Penjualan Produk', 'type' => 'income', 'icon' => 'shopping-bag', 'sort_order' => 1],
            ['name' => 'Penjualan Jasa', 'type' => 'income', 'icon' => 'briefcase', 'sort_order' => 2],
            ['name' => 'Pendapatan Lain-lain', 'type' => 'income', 'icon' => 'cash', 'sort_order' => 3],
            ['name' => 'Bahan Baku & Material', 'type' => 'expense', 'icon' => 'box', 'sort_order' => 1],
            ['name' => 'Operasional & Utilitas', 'type' => 'expense', 'icon' => 'lightning-bolt', 'sort_order' => 2],
            ['name' => 'Gaji Karyawan', 'type' => 'expense', 'icon' => 'users', 'sort_order' => 3],
            ['name' => 'Pemasaran & Iklan', 'type' => 'expense', 'icon' => 'megaphone', 'sort_order' => 4],
            ['name' => 'Sewa Tempat', 'type' => 'expense', 'icon' => 'home', 'sort_order' => 5],
            ['name' => 'Transportasi & Logistik', 'type' => 'expense', 'icon' => 'truck', 'sort_order' => 6],
            ['name' => 'Pengeluaran Lain-lain', 'type' => 'expense', 'icon' => 'tag', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            $exists = DB::table('categories')
                ->where('name', $category['name'])
                ->where('is_system', true)
                ->exists();

            if (!$exists) {
                DB::table('categories')->insert(array_merge($category, [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => null,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
