<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo dataset for screenshots and walkthroughs.
 *
 * Context: Batik Nusantara, a batik shop in Kampung Batik Laweyan, Solo.
 * Every module (SiKas, SiStok, SiPasar, SiPromo) gets coherent data - the
 * numbers line up across modules: sales draw down the stock they sold,
 * reports match the transactions in their period, and the market analysis
 * sits on the real coordinates of the outlet.
 *
 * Run: php artisan db:seed --class=DemoBatikNusantaraSeeder
 */
class DemoBatikNusantaraSeeder extends Seeder
{
    private const EMAIL = 'demo@batiknusantara.id';
    private const PASSWORD = 'batik2026';

    // Kampung Batik Laweyan, Surakarta
    private const LAT = -7.566700;
    private const LNG = 110.796000;

    private string $umkmId;
    private string $userId;

    /** @var array<string, string> */
    private array $outlets = [];

    /** @var array<string, string> */
    private array $categories = [];

    /** @var array<string, array{id: string, name: string, price: int, stock: int, threshold: int}> */
    private array $products = [];

    public function run(): void
    {
        $this->command->info('Seeding demo UMKM: Batik Nusantara (Laweyan, Solo)...');

        DB::transaction(function () {
            $this->reset();
            $this->seedUserAndProfile();
            $this->seedOutlets();
            $this->seedCategories();
            $this->seedProducts();
            $this->seedTransactions();
            $this->seedReports();
            $this->seedMarketAnalyses();
            $this->seedContentAssets();
            $this->seedInvites();
        });

        $this->command->info('Done. Login: ' . self::EMAIL . ' / ' . self::PASSWORD);
    }

    /**
     * Wipe any previous run of this seeder so it can be re-run safely.
     * Only touches rows owned by the demo account.
     */
    private function reset(): void
    {
        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');
        if (! $userId) {
            return;
        }

        $umkmIds = DB::table('umkm_profiles')->where('user_id', $userId)->pluck('id')->all();

        if ($umkmIds) {
            $analysisIds = DB::table('market_analyses')->whereIn('umkm_id', $umkmIds)->pluck('id')->all();
            $contentIds = DB::table('content_assets')->whereIn('umkm_id', $umkmIds)->pluck('id')->all();

            if ($contentIds) {
                DB::table('publish_jobs')->whereIn('content_id', $contentIds)->delete();
            }
            if ($analysisIds) {
                DB::table('competitors')->whereIn('analysis_id', $analysisIds)->delete();
                DB::table('demographics')->whereIn('analysis_id', $analysisIds)->delete();
            }

            DB::table('transactions')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('content_assets')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('market_analyses')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('reports')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('invites')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('products')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('categories')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('outlets')->whereIn('umkm_id', $umkmIds)->delete();
            DB::table('umkm_profiles')->whereIn('id', $umkmIds)->delete();
        }

        DB::table('users')->where('id', $userId)->delete();
    }

    private function seedUserAndProfile(): void
    {
        $this->userId = (string) Str::uuid();
        $this->umkmId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $this->userId,
            'email' => self::EMAIL,
            'full_name' => 'Sri Wahyuni',
            'phone' => '081228845770',
            'role' => 'owner',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now()->subMonths(8),
            'created_at' => now()->subMonths(8),
            'updated_at' => now(),
        ]);

        DB::table('umkm_profiles')->insert([
            'id' => $this->umkmId,
            'user_id' => $this->userId,
            'business_name' => 'Batik Nusantara',
            'business_type' => 'Fashion & Kerajinan Batik',
            'address' => 'Jl. Dr. Radjiman No. 521, Kampung Batik Laweyan',
            'city' => 'Surakarta',
            'province' => 'Jawa Tengah',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'phone' => '081228845770',
            'profile_completeness' => 100,
            'target_cuan' => 45000000,
            'target_cuan_period' => 'monthly',
            'financial_settings' => json_encode([
                'currency' => 'IDR',
                'tax_rate' => 0.005,
                'fiscal_month_start' => 1,
                'default_payment_method' => 'qris',
            ]),
            'created_at' => now()->subMonths(8),
            'updated_at' => now(),
        ]);
    }

    private function seedOutlets(): void
    {
        $rows = [
            ['Laweyan (Pusat)', 'Jl. Dr. Radjiman No. 521, Laweyan, Surakarta', self::LAT, self::LNG, true],
            ['Kios Pasar Klewer', 'Pasar Klewer Blok B2 No. 14, Gajahan, Surakarta', -7.575800, 110.826900, false],
            ['Galeri Malioboro', 'Jl. Malioboro No. 88, Yogyakarta', -7.792900, 110.365800, false],
        ];

        foreach ($rows as [$name, $address, $lat, $lng, $primary]) {
            $id = (string) Str::uuid();
            $this->outlets[$name] = $id;

            DB::table('outlets')->insert([
                'id' => $id,
                'umkm_id' => $this->umkmId,
                'name' => $name,
                'address' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'is_primary' => $primary,
                'created_at' => now()->subMonths(8),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCategories(): void
    {
        $rows = [
            ['Penjualan Batik Tulis', 'income', 'shopping-bag', 1],
            ['Penjualan Batik Cap', 'income', 'shopping-bag', 2],
            ['Seragam & Pesanan Custom', 'income', 'briefcase', 3],
            ['Penjualan Marketplace', 'income', 'cash', 4],
            ['Kain Mori & Bahan Baku', 'expense', 'box', 1],
            ['Lilin Malam & Pewarna', 'expense', 'box', 2],
            ['Upah Pembatik', 'expense', 'users', 3],
            ['Sewa Kios & Listrik', 'expense', 'home', 4],
            ['Ongkir & Packing', 'expense', 'truck', 5],
            ['Promosi & Pameran', 'expense', 'megaphone', 6],
        ];

        foreach ($rows as [$name, $type, $icon, $order]) {
            $id = (string) Str::uuid();
            $this->categories[$name] = $id;

            DB::table('categories')->insert([
                'id' => $id,
                'umkm_id' => $this->umkmId,
                'name' => $name,
                'type' => $type,
                'icon' => $icon,
                'sort_order' => $order,
                'is_system' => false,
                'created_at' => now()->subMonths(8),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedProducts(): void
    {
        // [name, sku, category, price, opening stock, low-stock threshold]
        $rows = [
            ['Batik Tulis Sogan Klasik', 'BTK-0001', 'textiles', 850000, 24, 5],
            ['Batik Tulis Parang Rusak', 'BTK-0002', 'textiles', 1250000, 14, 3],
            ['Batik Tulis Sekar Jagad', 'BTK-0003', 'textiles', 1450000, 10, 3],
            ['Batik Cap Kawung Halus', 'BTK-0004', 'textiles', 285000, 62, 10],
            ['Batik Cap Truntum', 'BTK-0005', 'textiles', 265000, 58, 10],
            ['Kemeja Batik Pria Lengan Panjang', 'BTK-0006', 'textiles', 320000, 48, 8],
            ['Blouse Batik Wanita Modern', 'BTK-0007', 'textiles', 295000, 40, 8],
            ['Kain Jarik Batik Pekalongan', 'BTK-0008', 'textiles', 180000, 72, 12],
            ['Selendang Batik Sutra', 'BTK-0009', 'textiles', 425000, 20, 5],
            ['Seragam Batik Kantor (per set)', 'BTK-0010', 'textiles', 240000, 95, 15],
            ['Dompet Batik Handmade', 'AKS-0011', 'handicrafts', 85000, 44, 10],
            ['Tas Tote Batik Kanvas', 'AKS-0012', 'handicrafts', 145000, 30, 8],
            ['Masker Batik (isi 3)', 'AKS-0013', 'handicrafts', 45000, 18, 15],
            ['Jasa Custom Motif Perusahaan', 'JSA-0014', 'services', 3500000, 6, 2],
        ];

        foreach ($rows as [$name, $sku, $category, $price, $stock, $threshold]) {
            $id = (string) Str::uuid();
            $this->products[$sku] = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'stock' => $stock,
                'threshold' => $threshold,
            ];

            DB::table('products')->insert([
                'id' => $id,
                'umkm_id' => $this->umkmId,
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'price' => $price,
                'stock_level' => $stock,
                'status' => 'in_stock',
                'description' => $this->productDescription($name, $category),
                'low_stock_threshold' => $threshold,
                'created_at' => now()->subMonths(6),
                'updated_at' => now(),
            ]);
        }
    }


    /**
     * Short factual blurbs — the SiPromo pipeline grounds its copy on these.
     */
    private function productDescription(string $name, string $category): string
    {
        return match (true) {
            str_contains($name, 'Batik Tulis') => 'Batik tulis tangan pembatik Laweyan di atas kain katun primissima, pewarnaan soga alami, proses sekitar tiga minggu per helai. Ukuran 2,4 x 1,05 meter.',
            str_contains($name, 'Batik Cap') => 'Batik cap dengan stempel tembaga, kain katun prima, melalui proses malam dan pewarnaan penuh. Ukuran 2,4 x 1,05 meter.',
            str_contains($name, 'Kemeja') => 'Kemeja batik pria lengan panjang, katun adem, jahitan rapi dengan kancing kayu. Tersedia ukuran S hingga XXL.',
            str_contains($name, 'Blouse') => 'Blouse batik wanita potongan modern, katun viscose jatuh, cocok untuk kerja maupun acara semi formal. Ukuran S hingga XL.',
            str_contains($name, 'Jarik') => 'Kain jarik batik Pekalongan, motif pesisiran cerah, panjang 2,5 meter, cocok untuk kebaya dan gendongan.',
            str_contains($name, 'Selendang') => 'Selendang batik berbahan sutra, ringan dan jatuh, panjang 2 meter dengan motif serasi koleksi sogan.',
            str_contains($name, 'Seragam') => 'Seragam batik kantor per set, kain katun prima, motif dapat dikustom sesuai identitas instansi. Minimum pesanan 20 set.',
            str_contains($name, 'Dompet') => 'Dompet handmade berbahan sisa kain batik pilihan, dilapis kanvas, dua slot kartu dan satu ritsleting.',
            str_contains($name, 'Tas Tote') => 'Tas tote kanvas dengan panel batik, jahitan rangkap, muat laptop 14 inci.',
            str_contains($name, 'Masker') => 'Masker kain batik tiga lapis, isi tiga per paket, tali elastis yang bisa disetel.',
            str_contains($name, 'Jasa Custom') => 'Layanan perancangan motif batik khusus untuk perusahaan: konsultasi, sampel kain, hingga produksi massal.',
            default => 'Produk koleksi Batik Nusantara, Kampung Batik Laweyan, Surakarta.',
        };
    }

    /**
     * Roughly 90 days of trading. Sales are booked against real products so
     * SiStok stock levels are the arithmetic result of what was sold.
     */
    private function seedTransactions(): void
    {
        $sold = [];
        $rows = [];

        // Sales pattern per weekday: batik shops peak on weekends and paydays.
        $skuPool = [
            'BTK-0004' => ['qty' => [1, 3], 'weight' => 5],
            'BTK-0005' => ['qty' => [1, 3], 'weight' => 5],
            'BTK-0006' => ['qty' => [1, 2], 'weight' => 4],
            'BTK-0007' => ['qty' => [1, 2], 'weight' => 4],
            'BTK-0008' => ['qty' => [1, 4], 'weight' => 4],
            'BTK-0010' => ['qty' => [2, 6], 'weight' => 3],
            'AKS-0011' => ['qty' => [1, 4], 'weight' => 3],
            'AKS-0012' => ['qty' => [1, 2], 'weight' => 3],
            'AKS-0013' => ['qty' => [1, 3], 'weight' => 2],
            'BTK-0001' => ['qty' => [1, 1], 'weight' => 2],
            'BTK-0002' => ['qty' => [1, 1], 'weight' => 1],
            'BTK-0003' => ['qty' => [1, 1], 'weight' => 1],
            'BTK-0009' => ['qty' => [1, 2], 'weight' => 2],
        ];

        $weighted = [];
        foreach ($skuPool as $sku => $cfg) {
            $weighted = array_merge($weighted, array_fill(0, $cfg['weight'], $sku));
        }

        $paymentMix = ['qris', 'qris', 'cash', 'cash', 'transfer', 'ewallet'];
        $buyers = [
            'Pembeli walk-in', 'Rombongan wisata Solo', 'Pesanan WhatsApp',
            'Reseller Jogja', 'Pelanggan Tokopedia', 'Pelanggan Shopee',
        ];

        for ($day = 89; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day);
            $isWeekend = $date->isWeekend();
            // The two most recent days drive the dashboard's day-over-day delta,
            // so keep them comparable instead of wildly different.
            $salesToday = $day <= 1
                ? random_int(4, 5)
                : ($isWeekend ? random_int(3, 6) : random_int(1, 4));

            for ($i = 0; $i < $salesToday; $i++) {
                $sku = $weighted[array_rand($weighted)];
                $product = $this->products[$sku];
                [$minQty, $maxQty] = $skuPool[$sku]['qty'];
                $qty = random_int($minQty, $maxQty);

                $sold[$sku] = ($sold[$sku] ?? 0) + $qty;

                $payment = $paymentMix[array_rand($paymentMix)];
                $isOnline = in_array($payment, ['transfer', 'ewallet'], true);
                $category = $isOnline
                    ? 'Penjualan Marketplace'
                    : (str_starts_with($sku, 'BTK-000') && (int) substr($sku, -1) <= 3
                        ? 'Penjualan Batik Tulis'
                        : 'Penjualan Batik Cap');

                $outlet = $isWeekend && $i % 3 === 0 ? 'Kios Pasar Klewer' : 'Laweyan (Pusat)';
                $source = $payment === 'qris' ? 'qris' : ($i === 0 && $day % 7 === 0 ? 'voice' : 'manual');

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $this->outlets[$outlet],
                    'category_id' => $this->categories[$category],
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'type' => 'income',
                    'amount' => $product['price'] * $qty,
                    'description' => 'Penjualan ' . $product['name'] . ' x' . $qty,
                    'notes' => $buyers[array_rand($buyers)],
                    'source' => $source,
                    'payment_method' => $payment,
                    'merchant_name' => $isOnline ? 'Marketplace' : 'Kasir ' . $outlet,
                    'ai_metadata' => $source === 'voice' ? json_encode([
                        'transcribed_text' => 'jual ' . strtolower($product['name']) . ' ' . $qty . ' potong',
                        'confidence' => 0.93,
                        'model' => 'whisper-1',
                    ]) : '{}',
                    'is_verified' => $payment !== 'qris' || $day > 2,
                    'transaction_date' => $date->copy()->setTime(random_int(9, 19), random_int(0, 59)),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }

            // Custom uniform orders land a few times a month and are big tickets.
            // Keep the big corporate orders off the two most recent days so the
            // dashboard's day-over-day comparison stays readable.
            if ($day % 23 === 5) {
                $qty = random_int(20, 45);
                $product = $this->products['BTK-0010'];
                $sold['BTK-0010'] = ($sold['BTK-0010'] ?? 0) + $qty;

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $this->outlets['Laweyan (Pusat)'],
                    'category_id' => $this->categories['Seragam & Pesanan Custom'],
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'type' => 'income',
                    'amount' => $product['price'] * $qty,
                    'description' => 'Seragam batik kantor x' . $qty . ' set',
                    'notes' => 'Pesanan instansi - pelunasan termin akhir',
                    'source' => 'manual',
                    'payment_method' => 'transfer',
                    'merchant_name' => 'Pesanan Korporat',
                    'ai_metadata' => '{}',
                    'is_verified' => true,
                    'transaction_date' => $date->copy()->setTime(14, 30),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        // Recurring costs.
        $rows = array_merge($rows, $this->buildExpenses());

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        $this->applyStockFromSales($sold);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildExpenses(): array
    {
        $rows = [];
        $primary = $this->outlets['Laweyan (Pusat)'];

        for ($month = 2; $month >= 0; $month--) {
            $anchor = Carbon::today()->subMonths($month);

            $fixed = [
                ['Sewa Kios & Listrik', 'Sewa kios Pasar Klewer + listrik', 4200000, 3, 'transfer'],
                ['Upah Pembatik', 'Upah 6 pembatik tulis (borongan)', 12600000, 5, 'transfer'],
                ['Kain Mori & Bahan Baku', 'Kain mori primissima 120 meter', 7800000, 8, 'transfer'],
                ['Lilin Malam & Pewarna', 'Lilin malam, naptol, indigosol', 2350000, 9, 'cash'],
                ['Promosi & Pameran', 'Sewa booth pameran kriya + banner', 3100000, 14, 'transfer'],
                ['Ongkir & Packing', 'Ongkir marketplace + kardus packing', 1450000, 20, 'ewallet'],
            ];

            foreach ($fixed as [$category, $description, $amount, $dayOfMonth, $payment]) {
                $date = $anchor->copy()->startOfMonth()->addDays($dayOfMonth - 1);
                if ($date->isFuture()) {
                    continue;
                }

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $primary,
                    'category_id' => $this->categories[$category],
                    'product_id' => null,
                    'quantity' => null,
                    'type' => 'expense',
                    'amount' => $amount + random_int(-150000, 150000),
                    'description' => $description,
                    'notes' => null,
                    'source' => 'manual',
                    'payment_method' => $payment,
                    'merchant_name' => null,
                    'ai_metadata' => '{}',
                    'is_verified' => true,
                    'transaction_date' => $date->copy()->setTime(random_int(8, 16), 0),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        return $rows;
    }

    /**
     * Stock on hand = opening stock - units sold, restocked when it would go
     * negative, so SiStok shows a believable mix of healthy and thin stock.
     *
     * @param array<string, int> $sold
     */
    private function applyStockFromSales(array $sold): void
    {
        foreach ($this->products as $sku => $product) {
            $unitsSold = $sold[$sku] ?? 0;
            $remaining = $product['stock'] - $unitsSold;

            // Restock in batches whenever the shelf would have run dry.
            $restocks = 0;
            while ($remaining < 0) {
                $remaining += max(20, $product['stock']);
                $restocks++;
            }

            // Leave a couple of items deliberately thin for the low-stock views.
            if (in_array($sku, ['AKS-0013', 'BTK-0003'], true)) {
                $remaining = min($remaining, $product['threshold'] - 1);
            }
            if ($sku === 'BTK-0002') {
                $remaining = 0;
            }

            $remaining = max(0, $remaining);
            $status = $remaining === 0
                ? 'out_of_stock'
                : ($remaining <= $product['threshold'] ? 'low_stock' : 'in_stock');

            DB::table('products')->where('id', $product['id'])->update([
                'stock_level' => $remaining,
                'status' => $status,
                'updated_at' => now(),
            ]);

            if ($restocks > 0) {
                $this->recordRestock($product, $restocks);
            }
        }
    }

    /**
     * @param array{id: string, name: string, price: int, stock: int, threshold: int} $product
     */
    private function recordRestock(array $product, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $date = Carbon::today()->subDays(random_int(5, 80));
            $qty = max(20, $product['stock']);

            DB::table('transactions')->insert([
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'outlet_id' => $this->outlets['Laweyan (Pusat)'],
                'category_id' => $this->categories['Kain Mori & Bahan Baku'],
                'product_id' => $product['id'],
                'quantity' => $qty,
                'type' => 'expense',
                // Cost price sits around 60% of the shelf price.
                'amount' => (int) round($product['price'] * 0.6) * $qty,
                'description' => 'Restock ' . $product['name'] . ' x' . $qty,
                'notes' => 'Produksi batch workshop Laweyan',
                'source' => 'manual',
                'payment_method' => 'transfer',
                'merchant_name' => 'Workshop Laweyan',
                'ai_metadata' => '{}',
                'is_verified' => true,
                'transaction_date' => $date->copy()->setTime(10, 0),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function seedReports(): void
    {
        for ($month = 2; $month >= 0; $month--) {
            $start = Carbon::today()->subMonths($month)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            if ($end->isFuture()) {
                $end = Carbon::today();
            }

            $scope = DB::table('transactions')
                ->where('umkm_id', $this->umkmId)
                ->whereBetween('transaction_date', [$start, (clone $end)->endOfDay()]);

            $income = (int) (clone $scope)->where('type', 'income')->sum('amount');
            $expense = (int) (clone $scope)->where('type', 'expense')->sum('amount');
            $count = (int) (clone $scope)->count();

            $topProducts = DB::table('transactions')
                ->join('products', 'products.id', '=', 'transactions.product_id')
                ->where('transactions.umkm_id', $this->umkmId)
                ->where('transactions.type', 'income')
                ->whereBetween('transactions.transaction_date', [$start, (clone $end)->endOfDay()])
                ->groupBy('products.name')
                ->selectRaw('products.name, sum(transactions.quantity) as units, sum(transactions.amount) as revenue')
                ->orderByDesc('revenue')
                ->limit(3)
                ->get()
                ->map(fn ($row) => [
                    'product' => $row->name,
                    'units' => (int) $row->units,
                    'revenue' => (int) $row->revenue,
                ])->all();

            DB::table('reports')->insert([
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'type' => 'monthly',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'total_income' => $income,
                'total_expense' => $expense,
                'net_profit' => $income - $expense,
                'transaction_count' => $count,
                'data' => json_encode([
                    'top_products' => $topProducts,
                    'margin_percent' => $income > 0 ? round((($income - $expense) / $income) * 100, 1) : 0,
                    'busiest_channel' => 'QRIS',
                    'notes' => 'Ringkasan otomatis dari transaksi SiKas.',
                ]),
                'file_url' => null,
                'created_at' => $end,
                'updated_at' => $end,
            ]);
        }
    }

    private function seedMarketAnalyses(): void
    {
        $analyses = [
            [
                'query' => 'Kampung Batik Laweyan, Surakarta',
                'lat' => self::LAT,
                'lng' => self::LNG,
                'radius' => 1.0,
                'score' => 78,
                'competition' => 'tinggi',
                'days_ago' => 3,
                'area' => 'Kecamatan Laweyan',
                'population' => 98650,
                'competitors' => [
                    ['Batik Putra Laweyan', 4.6, 412, 'positive', -7.5661, 110.7948],
                    ['Batik Merak Manis', 4.4, 268, 'positive', -7.5673, 110.7971],
                    ['Batik Mahkota Laweyan', 4.7, 531, 'positive', -7.5669, 110.7935],
                    ['Batik Gunawan Setiawan', 4.3, 197, 'neutral', -7.5682, 110.7982],
                    ['Griya Batik Cempaka', 3.9, 84, 'neutral', -7.5655, 110.7959],
                    ['Batik Puspa Kencana', 4.1, 126, 'positive', -7.5688, 110.7944],
                ],
            ],
            [
                'query' => 'Pasar Klewer, Surakarta',
                'lat' => -7.575800,
                'lng' => 110.826900,
                'radius' => 1.5,
                'score' => 64,
                'competition' => 'sangat tinggi',
                'days_ago' => 12,
                'area' => 'Kecamatan Pasar Kliwon',
                'population' => 87420,
                'competitors' => [
                    ['Toko Batik Semar Klewer', 4.2, 356, 'positive', -7.5761, 110.8272],
                    ['Batik Keris Klewer', 4.5, 620, 'positive', -7.5755, 110.8265],
                    ['Grosir Batik Solo Indah', 3.8, 143, 'neutral', -7.5772, 110.8281],
                    ['Batik Danar Hadi Outlet', 4.6, 894, 'positive', -7.5744, 110.8259],
                    ['Kios Batik Sekar Arum', 3.6, 47, 'negative', -7.5769, 110.8290],
                ],
            ],
            [
                'query' => 'Malioboro, Yogyakarta',
                'lat' => -7.792900,
                'lng' => 110.365800,
                'radius' => 2.0,
                'score' => 85,
                'competition' => 'sedang',
                'days_ago' => 27,
                'area' => 'Kecamatan Gedongtengen',
                'population' => 45180,
                'competitors' => [
                    ['Batik Hamzah Malioboro', 4.4, 508, 'positive', -7.7935, 110.3661],
                    ['Mirota Batik', 4.5, 1240, 'positive', -7.7921, 110.3655],
                    ['Batik Terang Bulan', 4.0, 172, 'neutral', -7.7944, 110.3648],
                    ['Toko Batik Ratna', 3.7, 63, 'neutral', -7.7912, 110.3672],
                ],
            ],
        ];

        foreach ($analyses as $item) {
            $analysisId = (string) Str::uuid();
            $createdAt = Carbon::today()->subDays($item['days_ago'])->setTime(10, 15);

            DB::table('market_analyses')->insert([
                'id' => $analysisId,
                'umkm_id' => $this->umkmId,
                'location_query' => $item['query'],
                'latitude' => $item['lat'],
                'longitude' => $item['lng'],
                'radius_km' => $item['radius'],
                'market_fit_score' => $item['score'],
                'analysis_data' => json_encode([
                    'category' => 'retail_fashion',
                    'competition_level' => $item['competition'],
                    'competition_score' => round(count($item['competitors']) / 10, 3),
                    'competition_density_per_km2' => round(count($item['competitors']) / max($item['radius'], 0.1), 2),
                    'market_potential_label' => $item['score'] >= 75 ? 'tinggi' : ($item['score'] >= 50 ? 'sedang' : 'rendah'),
                    'market_potential_narrative' => $this->narrative($item),
                    'data_source' => 'osm',
                    'source' => 'ai-sipasar-python',
                ]),
                'demographic_data' => json_encode([
                    'population' => $item['population'],
                    'density' => $item['population'] > 90000 ? 'padat' : 'sedang',
                    'economic_indicator' => $item['score'] >= 75 ? 'menengah-atas' : 'menengah',
                    'dominant_consumer_segment' => 'wisatawan_dan_pekerja',
                ]),
                'status' => 'completed',
                'expires_at' => $createdAt->copy()->addDays(30),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($item['competitors'] as [$name, $rating, $reviews, $sentiment, $lat, $lng]) {
                DB::table('competitors')->insert([
                    'id' => (string) Str::uuid(),
                    'analysis_id' => $analysisId,
                    'name' => $name,
                    'business_type' => 'Toko Batik',
                    'address' => $item['area'] . ', ' . ($item['query'] === 'Malioboro, Yogyakarta' ? 'Yogyakarta' : 'Surakarta'),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'rating' => $rating,
                    'review_count' => $reviews,
                    'sentiment' => $sentiment,
                    'scraped_data' => json_encode([
                        'price_range' => $rating >= 4.4 ? 'Rp150.000 - Rp2.500.000' : 'Rp75.000 - Rp900.000',
                        'opening_hours' => '09:00 - 21:00',
                        'source' => 'openstreetmap',
                    ]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            DB::table('demographics')->insert([
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'analysis_id' => $analysisId,
                'area_name' => $item['area'],
                'population_data' => json_encode([
                    'total' => $item['population'],
                    'households' => (int) round($item['population'] / 3.8),
                    'density_per_km2' => (int) round($item['population'] / 8.5),
                ]),
                'income_data' => json_encode([
                    'average_monthly_idr' => $item['score'] >= 75 ? 4850000 : 3650000,
                    'purchasing_power_index' => $item['score'] >= 75 ? 0.72 : 0.58,
                ]),
                'age_distribution' => json_encode([
                    '0-14' => 21.4,
                    '15-24' => 17.8,
                    '25-44' => 32.6,
                    '45-64' => 20.1,
                    '65+' => 8.1,
                ]),
                'data_source' => 'bps',
                'fetched_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function narrative(array $item): string
    {
        return 'Radius ' . $item['radius'] . ' km di sekitar ' . $item['query'] . ' memiliki '
            . count($item['competitors']) . ' toko batik aktif dengan tingkat persaingan ' . $item['competition']
            . '. Populasi sekitar ' . number_format($item['population'], 0, ',', '.')
            . ' jiwa dengan segmen dominan wisatawan dan pekerja kantoran. Skor potensi pasar '
            . $item['score'] . '/100.';
    }

    private function seedContentAssets(): void
    {
        $contents = [
            [
                'title' => 'Promo Batik Tulis Sogan Klasik',
                'type' => 'social_media',
                'status' => 'published',
                'days_ago' => 4,
                'prompt' => 'Buat caption Instagram untuk promo batik tulis sogan klasik, tone hangat dan elegan',
                'caption' => "Sogan klasik yang tidak pernah kehilangan wibawanya.\n\nSetiap helai Batik Tulis Sogan kami dikerjakan tangan pembatik Laweyan selama kurang lebih tiga minggu — dari nyanting, nembok, sampai pewarnaan soga alami. Warna cokelatnya justru makin dalam seiring waktu.\n\nTersedia di galeri Laweyan dan Pasar Klewer.",
                'hashtags' => ['#BatikNusantara', '#BatikTulis', '#KampungBatikLaweyan', '#BatikSolo', '#WastraIndonesia'],
                'tone' => 'hangat',
                'style' => 'elegan',
                'platform' => 'instagram',
            ],
            [
                'title' => 'Seragam Batik Kantor - Penawaran Korporat',
                'type' => 'ad_copy',
                'status' => 'published',
                'days_ago' => 11,
                'prompt' => 'Iklan singkat untuk penawaran seragam batik kantor kepada instansi',
                'caption' => "Seragam batik kantor, dijahit rapi, dikirim tepat waktu.\n\nMinimal 20 set, motif bisa dikustom sesuai identitas instansi. Sudah dipercaya kantor kelurahan, sekolah, dan koperasi di Solo Raya.\n\nHubungi 0812-2884-5770 untuk katalog dan sampel kain.",
                'hashtags' => ['#SeragamBatik', '#BatikKantor', '#BatikCustom', '#UMKMSolo'],
                'tone' => 'profesional',
                'style' => 'informatif',
                'platform' => 'facebook',
            ],
            [
                'title' => 'Edukasi: Beda Batik Tulis, Cap, dan Print',
                'type' => 'blog_post',
                'status' => 'published',
                'days_ago' => 19,
                'prompt' => 'Artikel pendek yang menjelaskan perbedaan batik tulis, cap, dan print untuk pembeli awam',
                'caption' => "Banyak yang bertanya kenapa harga batik bisa berbeda jauh. Jawabannya ada pada proses.\n\nBatik tulis dikerjakan helai demi helai dengan canting — satu kain bisa memakan waktu berminggu-minggu. Batik cap memakai stempel tembaga, lebih cepat namun tetap melalui proses malam dan pewarnaan. Sementara kain bermotif batik yang dicetak mesin sebenarnya bukan batik, melainkan tekstil bermotif.\n\nMengenali perbedaannya membantu Anda menghargai kerja para pembatik.",
                'hashtags' => ['#EdukasiBatik', '#BatikTulis', '#BatikCap', '#WarisanBudaya'],
                'tone' => 'edukatif',
                'style' => 'naratif',
                'platform' => null,
            ],
            [
                'title' => 'Koleksi Lebaran - Kemeja & Blouse Batik',
                'type' => 'social_media',
                'status' => 'draft',
                'days_ago' => 1,
                'prompt' => 'Caption koleksi lebaran untuk kemeja dan blouse batik modern, tone ceria',
                'caption' => "Sarimbit lebaran sudah siap!\n\nKemeja pria lengan panjang dan blouse wanita dengan motif senada — cocok untuk foto keluarga yang seragam tanpa terasa kaku. Ukuran S sampai XXL.\n\nStok terbatas, siapkan dari sekarang sebelum ramai.",
                'hashtags' => ['#SarimbitLebaran', '#BatikKeluarga', '#BatikModern', '#BatikNusantara'],
                'tone' => 'ceria',
                'style' => 'kasual',
                'platform' => null,
            ],
            [
                'title' => 'Behind the Scene: Sehari Bersama Pembatik',
                'type' => 'social_media',
                'status' => 'draft',
                'days_ago' => 2,
                'prompt' => 'Caption dokumentasi proses membatik di workshop Laweyan',
                'caption' => "Pukul enam pagi, canting sudah menyala.\n\nIbu Painem sudah 22 tahun membatik di workshop kami. Satu kain parang rusak bisa ia kerjakan sebulan penuh — dan ia masih hafal setiap lekuk motifnya tanpa pola.\n\nIni yang Anda bawa pulang setiap kali membeli batik tulis.",
                'hashtags' => ['#BehindTheScene', '#Pembatik', '#KampungBatikLaweyan', '#ProsesBatik'],
                'tone' => 'reflektif',
                'style' => 'dokumenter',
                'platform' => null,
            ],
            [
                'title' => 'Newsletter Pelanggan - Pameran Kriya Nusantara',
                'type' => 'email',
                'status' => 'archived',
                'days_ago' => 46,
                'prompt' => 'Email undangan pelanggan untuk mengunjungi booth pameran kriya',
                'caption' => "Kami akan hadir di Pameran Kriya Nusantara, Diamond Convention Center Solo.\n\nBooth A-14, membawa koleksi sekar jagad dan selendang sutra yang belum pernah kami pajang di galeri. Ada demo membatik setiap sore untuk pengunjung.\n\nSampai jumpa di sana.",
                'hashtags' => ['#PameranKriya', '#BatikNusantara', '#SoloEvent'],
                'tone' => 'formal',
                'style' => 'undangan',
                'platform' => null,
            ],
        ];

        foreach ($contents as $item) {
            $id = (string) Str::uuid();
            $createdAt = Carbon::today()->subDays($item['days_ago'])->setTime(9, 40);

            DB::table('content_assets')->insert([
                'id' => $id,
                'umkm_id' => $this->umkmId,
                'title' => $item['title'],
                'content_type' => $item['type'],
                'prompt' => $item['prompt'],
                'generated_text' => $item['caption'],
                'generated_image_url' => null,
                'caption' => $item['caption'],
                'hashtags' => json_encode($item['hashtags']),
                'brand_metadata' => json_encode([
                    'brand_voice' => 'hangat, menghormati proses, tidak berlebihan',
                    'palette' => ['sogan', 'indigo', 'krem'],
                    'grounded_on' => ['katalog produk', 'profil usaha'],
                    'requires_human_review' => true,
                ]),
                'tone' => $item['tone'],
                'style' => $item['style'],
                'version' => 1,
                'status' => $item['status'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($item['status'] === 'published' && $item['platform']) {
                DB::table('publish_jobs')->insert([
                    'id' => (string) Str::uuid(),
                    'content_id' => $id,
                    'platform' => $item['platform'],
                    'status' => 'published',
                    'platform_response' => json_encode([
                        'post_id' => strtoupper(Str::random(12)),
                        'permalink' => 'https://' . $item['platform'] . '.com/batiknusantara',
                        'reach' => random_int(800, 4200),
                        'engagement' => random_int(60, 480),
                    ]),
                    'scheduled_at' => $createdAt,
                    'published_at' => $createdAt->copy()->addMinutes(5),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function seedInvites(): void
    {
        $rows = [
            ['rina.kasir@batiknusantara.id', 'staff', 'accepted', 40],
            ['agus.gudang@batiknusantara.id', 'staff', 'pending', 4],
            ['auditor@koperasilaweyan.id', 'viewer', 'expired', 95],
        ];

        foreach ($rows as [$email, $role, $status, $daysAgo]) {
            $createdAt = Carbon::today()->subDays($daysAgo);

            DB::table('invites')->insert([
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'invited_by' => $this->userId,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'token' => Str::random(40),
                'expires_at' => $createdAt->copy()->addDays(14),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
