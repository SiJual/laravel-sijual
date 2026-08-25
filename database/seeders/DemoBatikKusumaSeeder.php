<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Second, independent demo account: Batik Kusuma Kauman, a larger and more
 * established batik business in Kampung Batik Kauman, Surakarta (the other
 * historic batik quarter in Solo besides Laweyan, right next to Keraton
 * Surakarta). Distinct from DemoBatikNusantaraSeeder — different owner,
 * different outlets, wider catalogue, six months of trading history with a
 * seasonal (Lebaran) sales spike, and its own market research and content.
 *
 * Run: php artisan db:seed --class=DemoBatikKusumaSeeder
 */
class DemoBatikKusumaSeeder extends Seeder
{
    private const EMAIL = 'demo@batikkusuma.id';
    private const PASSWORD = 'kusuma2026';

    // Kampung Batik Kauman, Surakarta (next to Keraton Surakarta)
    private const LAT = -7.573500;
    private const LNG = 110.826700;

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
        $this->command->info('Seeding demo UMKM: Batik Kusuma Kauman (Kauman, Solo)...');

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
            $this->seedMembership();
        });

        $this->command->info('Done. Login: ' . self::EMAIL . ' / ' . self::PASSWORD);
    }

    /**
     * Wipe any previous run of this seeder so it can be re-run safely.
     * Only touches rows owned by this demo account.
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
                DB::table('generation_runs')->whereIn('content_asset_id', $contentIds)->delete();
            }
            if ($analysisIds) {
                DB::table('competitors')->whereIn('analysis_id', $analysisIds)->delete();
                DB::table('demographics')->whereIn('analysis_id', $analysisIds)->delete();
            }

            DB::table('umkm_memberships')->whereIn('umkm_id', $umkmIds)->delete();
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
            'full_name' => 'Endang Kusumawati',
            'phone' => '081215567890',
            'role' => 'owner',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now()->subMonths(14),
            'created_at' => now()->subMonths(14),
            'updated_at' => now(),
        ]);

        DB::table('umkm_profiles')->insert([
            'id' => $this->umkmId,
            'user_id' => $this->userId,
            'business_name' => 'Batik Kusuma Kauman',
            'business_type' => 'Fashion & Kerajinan Batik',
            'address' => 'Jl. Cakra No. 12, Kampung Batik Kauman',
            'city' => 'Surakarta',
            'province' => 'Jawa Tengah',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'phone' => '081215567890',
            'profile_completeness' => 100,
            'target_cuan' => 85_000_000,
            'target_cuan_period' => 'monthly',
            'financial_settings' => json_encode([
                'currency' => 'IDR',
                'tax_rate' => 0.005,
                'fiscal_month_start' => 1,
                'default_payment_method' => 'qris',
            ]),
            'created_at' => now()->subMonths(14),
            'updated_at' => now(),
        ]);
    }

    private function seedOutlets(): void
    {
        $rows = [
            ['Kauman (Pusat)', 'Jl. Cakra No. 12, Kampung Batik Kauman, Surakarta', self::LAT, self::LNG, true],
            ['Kios Beteng Trade Center', 'Beteng Trade Center Lt. 2 Blok C-09, Sudiroprajan, Surakarta', -7.565800, 110.832100, false],
            ['Kios Pasar Beringharjo', 'Pasar Beringharjo Los Timur No. 45, Yogyakarta', -7.797200, 110.365400, false],
            ['Gudang Produksi Sukoharjo', 'Jl. Raya Solo-Sukoharjo Km 7, Grogol, Sukoharjo', -7.616700, 110.800000, false],
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
                'created_at' => now()->subMonths(14),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCategories(): void
    {
        $rows = [
            ['Penjualan Batik Tulis', 'income', 'shopping-bag', 1],
            ['Penjualan Batik Cap', 'income', 'shopping-bag', 2],
            ['Penjualan Batik Kombinasi', 'income', 'shopping-bag', 3],
            ['Seragam & Pesanan Custom', 'income', 'briefcase', 4],
            ['Penjualan Marketplace', 'income', 'cash', 5],
            ['Jasa Jahit & Permak', 'income', 'briefcase', 6],
            ['Kain Mori & Bahan Baku', 'expense', 'box', 1],
            ['Lilin Malam & Pewarna', 'expense', 'box', 2],
            ['Upah Pembatik', 'expense', 'users', 3],
            ['Upah Penjahit', 'expense', 'users', 4],
            ['Sewa Kios & Listrik', 'expense', 'home', 5],
            ['Ongkir & Packing', 'expense', 'truck', 6],
            ['Promosi & Pameran', 'expense', 'megaphone', 7],
            ['Perawatan Alat & Canting', 'expense', 'tag', 8],
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
                'created_at' => now()->subMonths(14),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedProducts(): void
    {
        // [name, sku, category, price, opening stock, low-stock threshold, description]
        $rows = [
            ['Batik Tulis Sutra Sekar Kawung Premium', 'KWN-0001', 'textiles', 3_500_000, 6, 2,
                'Batik tulis di atas kain sutra ATBM, motif kawung klasik, pewarnaan alami indigo dan soga. Proses sekitar dua bulan per helai. Ukuran 2,4 x 1,1 meter.'],
            ['Batik Tulis Sida Mukti Klasik', 'KWN-0002', 'textiles', 1_750_000, 10, 3,
                'Batik tulis motif sida mukti di atas katun primissima, dipakai turun-temurun untuk acara pernikahan adat Jawa. Ukuran 2,4 x 1,05 meter.'],
            ['Batik Tulis Truntum Kauman', 'KWN-0003', 'textiles', 1_450_000, 12, 3,
                'Batik tulis motif truntum, melambangkan cinta yang bersemi kembali, pewarnaan soga alami khas pembatik Kauman. Ukuran 2,4 x 1,05 meter.'],
            ['Batik Tulis Parang Kusumo', 'KWN-0004', 'textiles', 1_650_000, 9, 3,
                'Batik tulis motif parang kusumo, kain katun primissima, cocok untuk busana formal dan resepsi. Ukuran 2,4 x 1,05 meter.'],
            ['Batik Cap Kawung Prima', 'KWN-0005', 'textiles', 265_000, 70, 12,
                'Batik cap dengan stempel tembaga motif kawung, kain katun prima, proses pewarnaan penuh. Ukuran 2,4 x 1,05 meter.'],
            ['Batik Cap Sido Luhur', 'KWN-0006', 'textiles', 245_000, 65, 12,
                'Batik cap motif sido luhur, katun prima, warna cerah tahan luntur. Ukuran 2,4 x 1,05 meter.'],
            ['Batik Kombinasi Tulis-Cap Semar', 'KWN-0007', 'textiles', 520_000, 28, 6,
                'Perpaduan teknik cap untuk motif dasar dan tulis untuk isian detail, kain katun primissima. Ukuran 2,4 x 1,05 meter.'],
            ['Kemeja Batik Pria Katun Prima', 'KWN-0008', 'textiles', 285_000, 52, 10,
                'Kemeja batik pria lengan panjang, katun prima adem, kancing kayu, jahitan rapi. Ukuran S sampai XXL.'],
            ['Blouse Batik Wanita Kerja', 'KWN-0009', 'textiles', 275_000, 46, 8,
                'Blouse batik wanita potongan kerja, katun viscose jatuh, cocok untuk kantor. Ukuran S sampai XL.'],
            ['Dress Batik Wanita Pesta', 'KWN-0010', 'textiles', 495_000, 18, 5,
                'Dress batik wanita potongan modern untuk acara semi formal, kain katun sutra. Ukuran S sampai XL.'],
            ['Kain Jarik Batik Motif Klasik', 'KWN-0011', 'textiles', 195_000, 80, 15,
                'Kain jarik batik motif klasik Kauman, panjang 2,5 meter, untuk kebaya dan gendongan.'],
            ['Selendang Batik Sutra', 'KWN-0012', 'textiles', 385_000, 22, 5,
                'Selendang berbahan sutra dengan motif batik tulis tepi, panjang 2 meter.'],
            ['Seragam Batik Kantor (per set)', 'KWN-0013', 'textiles', 225_000, 110, 20,
                'Seragam batik kantor per set, katun prima, motif dapat dikustom sesuai identitas instansi. Minimum pesanan 20 set.'],
            ['Sarung Batik Pria', 'KWN-0014', 'textiles', 165_000, 40, 8,
                'Sarung batik pria motif kotak-kotak klasik, katun tenun, untuk ibadah dan santai.'],
            ['Dompet Batik Kulit Sintetis', 'AKS-0015', 'handicrafts', 95_000, 48, 10,
                'Dompet berbahan kulit sintetis dengan panel batik cap, tiga slot kartu dan satu ritsleting.'],
            ['Tas Selempang Batik', 'AKS-0016', 'handicrafts', 165_000, 32, 8,
                'Tas selempang kanvas dengan panel batik tulis, tali kulit sintetis, muat tablet 10 inci.'],
            ['Masker Batik (isi 5)', 'AKS-0017', 'handicrafts', 55_000, 60, 15,
                'Masker kain batik tiga lapis, isi lima per paket, tali elastis yang bisa disetel.'],
            ['Gantungan Kunci Batik', 'AKS-0018', 'handicrafts', 15_000, 150, 25,
                'Gantungan kunci kayu dilapis kain batik sisa produksi, oleh-oleh khas Kauman.'],
            ['Sarung Bantal Batik (sepasang)', 'AKS-0019', 'handicrafts', 125_000, 26, 6,
                'Sarung bantal sofa motif batik cap, sepasang, bahan katun tebal.'],
            ['Taplak Meja Batik', 'AKS-0020', 'handicrafts', 175_000, 20, 5,
                'Taplak meja batik ukuran 150x150 cm, motif kawung, katun tebal anti kusut.'],
            ['Jasa Custom Motif Perusahaan', 'JSA-0021', 'services', 4_500_000, 4, 1,
                'Layanan perancangan motif batik khusus untuk perusahaan atau instansi: konsultasi, sampel kain, hingga produksi massal.'],
            ['Jasa Jahit & Permak Busana', 'JSA-0022', 'services', 75_000, 999, 0,
                'Jasa jahit busana dari kain batik pelanggan, termasuk permak ukuran, estimasi 3-5 hari kerja.'],
        ];

        foreach ($rows as [$name, $sku, $category, $price, $stock, $threshold, $description]) {
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
                'description' => $description,
                'low_stock_threshold' => $threshold,
                'created_at' => now()->subMonths(12),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Six months of trading (~180 days), with a two-week Lebaran-style sales
     * surge roughly five months back, plus a pre-surge raw-material restock.
     * Sales are booked against real products so SiStok stock levels are the
     * arithmetic result of what was actually sold.
     */
    private function seedTransactions(): void
    {
        $sold = [];
        $rows = [];

        $skuPool = [
            'KWN-0005' => ['qty' => [1, 3], 'weight' => 6, 'category' => 'Penjualan Batik Cap'],
            'KWN-0006' => ['qty' => [1, 3], 'weight' => 6, 'category' => 'Penjualan Batik Cap'],
            'KWN-0008' => ['qty' => [1, 2], 'weight' => 5, 'category' => 'Penjualan Batik Cap'],
            'KWN-0009' => ['qty' => [1, 2], 'weight' => 5, 'category' => 'Penjualan Batik Cap'],
            'KWN-0011' => ['qty' => [1, 4], 'weight' => 5, 'category' => 'Penjualan Batik Cap'],
            'KWN-0014' => ['qty' => [1, 3], 'weight' => 4, 'category' => 'Penjualan Batik Cap'],
            'KWN-0007' => ['qty' => [1, 2], 'weight' => 4, 'category' => 'Penjualan Batik Kombinasi'],
            'KWN-0013' => ['qty' => [2, 8], 'weight' => 3, 'category' => 'Seragam & Pesanan Custom'],
            'AKS-0015' => ['qty' => [1, 4], 'weight' => 4, 'category' => 'Penjualan Marketplace'],
            'AKS-0016' => ['qty' => [1, 2], 'weight' => 4, 'category' => 'Penjualan Marketplace'],
            'AKS-0017' => ['qty' => [1, 3], 'weight' => 4, 'category' => 'Penjualan Marketplace'],
            'AKS-0018' => ['qty' => [2, 6], 'weight' => 5, 'category' => 'Penjualan Marketplace'],
            'AKS-0019' => ['qty' => [1, 2], 'weight' => 2, 'category' => 'Penjualan Marketplace'],
            'AKS-0020' => ['qty' => [1, 2], 'weight' => 2, 'category' => 'Penjualan Marketplace'],
            'KWN-0002' => ['qty' => [1, 1], 'weight' => 2, 'category' => 'Penjualan Batik Tulis'],
            'KWN-0003' => ['qty' => [1, 1], 'weight' => 2, 'category' => 'Penjualan Batik Tulis'],
            'KWN-0004' => ['qty' => [1, 1], 'weight' => 2, 'category' => 'Penjualan Batik Tulis'],
            'KWN-0001' => ['qty' => [1, 1], 'weight' => 1, 'category' => 'Penjualan Batik Tulis'],
            'KWN-0010' => ['qty' => [1, 2], 'weight' => 2, 'category' => 'Penjualan Batik Kombinasi'],
            'KWN-0012' => ['qty' => [1, 2], 'weight' => 2, 'category' => 'Penjualan Batik Tulis'],
            'JSA-0022' => ['qty' => [1, 3], 'weight' => 3, 'category' => 'Jasa Jahit & Permak'],
        ];

        $weighted = [];
        foreach ($skuPool as $sku => $cfg) {
            $weighted = array_merge($weighted, array_fill(0, $cfg['weight'], $sku));
        }

        $paymentMix = ['qris', 'qris', 'qris', 'cash', 'cash', 'transfer', 'ewallet'];
        $buyers = [
            'Bu Retno — pelanggan tetap', 'Rombongan reseller Semarang', 'Pesanan via WhatsApp',
            'Reseller Bandung', 'Reseller Jakarta', 'Turis domestik dari Jakarta',
            'Pembeli Tokopedia', 'Pembeli Shopee', 'Pembeli Instagram', 'Walk-in Beteng Trade Center',
            'Endorse selebgram lokal', 'Pelanggan lama, repeat order',
        ];
        $outletNames = array_keys($this->outlets);

        // The Lebaran surge window: roughly five months back from today.
        $surgeStart = Carbon::today()->subDays(155);
        $surgeEnd = $surgeStart->copy()->addDays(13);

        for ($day = 179; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day);
            $isWeekend = $date->isWeekend();
            $inSurge = $date->between($surgeStart, $surgeEnd);

            $salesToday = $inSurge
                ? random_int(9, 15)
                : ($isWeekend ? random_int(4, 8) : random_int(2, 5));

            for ($i = 0; $i < $salesToday; $i++) {
                $sku = $weighted[array_rand($weighted)];
                $cfg = $skuPool[$sku];
                $product = $this->products[$sku];
                [$minQty, $maxQty] = $cfg['qty'];
                $qty = $inSurge ? random_int($minQty, $maxQty + 1) : random_int($minQty, $maxQty);

                $sold[$sku] = ($sold[$sku] ?? 0) + $qty;

                $payment = $paymentMix[array_rand($paymentMix)];
                $isOnline = in_array($payment, ['transfer', 'ewallet'], true) || $cfg['category'] === 'Penjualan Marketplace';
                $category = $isOnline && $cfg['category'] !== 'Penjualan Marketplace' && $payment === 'transfer' && random_int(0, 2) === 0
                    ? 'Penjualan Marketplace'
                    : $cfg['category'];

                // Bulk reseller orders (qty 6+) get a negotiated per-unit rate.
                $unitPrice = $qty >= 6 ? (int) round($product['price'] * 0.88) : $product['price'];

                $outlet = $outletNames[array_rand($outletNames)];
                if ($outlet === 'Gudang Produksi Sukoharjo') {
                    // The warehouse doesn't sell walk-in; redirect to a real outlet.
                    $outlet = $isOnline ? 'Kauman (Pusat)' : $outletNames[array_rand(array_slice($outletNames, 0, 3))];
                }

                $source = $payment === 'qris' ? 'qris' : (($i === 0 && $day % 11 === 0) ? 'voice' : 'manual');

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $this->outlets[$outlet],
                    'category_id' => $this->categories[$category],
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'type' => 'income',
                    'amount' => $unitPrice * $qty,
                    'description' => 'Penjualan ' . $product['name'] . ' x' . $qty,
                    'notes' => $buyers[array_rand($buyers)] . ($inSurge ? ' (musim Lebaran)' : ''),
                    'source' => $source,
                    'payment_method' => $payment,
                    'merchant_name' => $isOnline ? 'Marketplace' : 'Kasir ' . $outlet,
                    'ai_metadata' => $source === 'voice' ? json_encode([
                        'transcribed_text' => 'jual ' . strtolower($product['name']) . ' ' . $qty . ' potong',
                        'confidence' => round(random_int(88, 98) / 100, 2),
                        'model' => 'whisper-1',
                    ]) : '{}',
                    'is_verified' => $payment !== 'qris' || $day > 2,
                    'transaction_date' => $date->copy()->setTime(random_int(9, 20), random_int(0, 59)),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }

            // Custom uniform / corporate orders land periodically and are big tickets.
            if ($day % 19 === 4) {
                $qty = random_int(25, 60);
                $product = $this->products['KWN-0013'];
                $sold['KWN-0013'] = ($sold['KWN-0013'] ?? 0) + $qty;

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $this->outlets['Kauman (Pusat)'],
                    'category_id' => $this->categories['Seragam & Pesanan Custom'],
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'type' => 'income',
                    'amount' => (int) round($product['price'] * 0.9) * $qty,
                    'description' => 'Seragam batik kantor x' . $qty . ' set',
                    'notes' => 'Pesanan instansi — pelunasan termin akhir',
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

            // The premium tulis pieces move rarely but are a meaningful ticket
            // when they do — a dedicated custom commission every few weeks.
            if ($day % 27 === 9) {
                $sku = random_int(0, 1) === 0 ? 'KWN-0001' : 'KWN-0002';
                $product = $this->products[$sku];
                $sold[$sku] = ($sold[$sku] ?? 0) + 1;

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'umkm_id' => $this->umkmId,
                    'outlet_id' => $this->outlets['Kauman (Pusat)'],
                    'category_id' => $this->categories['Penjualan Batik Tulis'],
                    'product_id' => $product['id'],
                    'quantity' => 1,
                    'type' => 'income',
                    'amount' => $product['price'],
                    'description' => 'Pesanan khusus ' . $product['name'],
                    'notes' => 'Pesanan untuk acara pernikahan adat',
                    'source' => 'manual',
                    'payment_method' => 'transfer',
                    'merchant_name' => 'Pesanan Khusus',
                    'ai_metadata' => '{}',
                    'is_verified' => true,
                    'transaction_date' => $date->copy()->setTime(11, 0),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        // Recurring and ad-hoc costs, including the pre-Lebaran material ramp-up.
        $rows = array_merge($rows, $this->buildExpenses($surgeStart));

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        $this->applyStockFromSales($sold);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildExpenses(Carbon $surgeStart): array
    {
        $rows = [];
        $primary = $this->outlets['Kauman (Pusat)'];

        for ($month = 5; $month >= 0; $month--) {
            $anchor = Carbon::today()->subMonths($month);

            // Retainer wages for the core team; piece-rate labor for
            // production is already priced into each restock's COGS below,
            // so this covers baseline staffing, not per-unit output.
            $fixed = [
                ['Sewa Kios & Listrik', 'Sewa 3 kios + listrik bulanan', 6_800_000, 3, 'transfer'],
                ['Upah Pembatik', 'Retainer 4 pembatik tetap', 12_000_000, 5, 'transfer'],
                ['Upah Penjahit', 'Retainer 2 penjahit tetap', 4_500_000, 5, 'transfer'],
                ['Kain Mori & Bahan Baku', 'Kain mori topup kebutuhan mendadak', 3_500_000, 8, 'transfer'],
                ['Lilin Malam & Pewarna', 'Lilin malam, naptol, indigosol, remasol', 3_200_000, 9, 'cash'],
                ['Promosi & Pameran', 'Iklan media sosial + sewa booth pameran', 3_200_000, 14, 'transfer'],
                ['Ongkir & Packing', 'Ongkir marketplace + kardus & bubble wrap', 2_400_000, 20, 'ewallet'],
                ['Perawatan Alat & Canting', 'Servis kompor batik dan canting', 750_000, 24, 'cash'],
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
                    'amount' => $amount + random_int(-250_000, 250_000),
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

        // Pre-Lebaran raw material ramp-up, a week before the surge window.
        $rampDate = $surgeStart->copy()->subDays(7);
        if (! $rampDate->isFuture()) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'outlet_id' => $primary,
                'category_id' => $this->categories['Kain Mori & Bahan Baku'],
                'product_id' => null,
                'quantity' => null,
                'type' => 'expense',
                'amount' => 14_000_000,
                'description' => 'Stok kain mori dan pewarna tambahan untuk produksi musim Lebaran',
                'notes' => 'Persiapan lonjakan permintaan sarimbit keluarga',
                'source' => 'manual',
                'payment_method' => 'transfer',
                'merchant_name' => 'Supplier Kain Mori Solo',
                'ai_metadata' => '{}',
                'is_verified' => true,
                'transaction_date' => $rampDate->copy()->setTime(10, 0),
                'created_at' => $rampDate,
                'updated_at' => $rampDate,
            ];

            $rows[] = [
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'outlet_id' => $primary,
                'category_id' => $this->categories['Upah Pembatik'],
                'product_id' => null,
                'quantity' => null,
                'type' => 'expense',
                'amount' => 4_500_000,
                'description' => 'Upah lembur tim pembatik menjelang Lebaran',
                'notes' => null,
                'source' => 'manual',
                'payment_method' => 'transfer',
                'merchant_name' => null,
                'ai_metadata' => '{}',
                'is_verified' => true,
                'transaction_date' => $rampDate->copy()->addDays(2)->setTime(9, 0),
                'created_at' => $rampDate,
                'updated_at' => $rampDate,
            ];
        }

        return $rows;
    }

    /**
     * Stock on hand = opening stock - units sold, restocked when it would go
     * negative, so SiStok shows a believable mix of healthy and thin stock.
     */
    private function applyStockFromSales(array $sold): void
    {
        foreach ($this->products as $sku => $product) {
            $unitsSold = $sold[$sku] ?? 0;
            $remaining = $product['stock'] - $unitsSold;

            $restocks = 0;
            while ($remaining < 0) {
                $remaining += max(25, $product['stock']);
                $restocks++;
            }

            // Leave a deliberate spread for the low-stock / out-of-stock views.
            if (in_array($sku, ['KWN-0001', 'AKS-0020'], true)) {
                $remaining = min($remaining, max(0, $product['threshold'] - 1));
            }
            if ($sku === 'KWN-0003') {
                $remaining = 0;
            }
            if ($sku === 'JSA-0022') {
                // A service line — "stock" is a booking slot count, not inventory.
                $remaining = 999;
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

            if ($restocks > 0 && $sku !== 'JSA-0022') {
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
            $date = Carbon::today()->subDays(random_int(5, 170));
            $qty = max(25, $product['stock']);

            DB::table('transactions')->insert([
                'id' => (string) Str::uuid(),
                'umkm_id' => $this->umkmId,
                'outlet_id' => $this->outlets['Kauman (Pusat)'],
                'category_id' => $this->categories['Kain Mori & Bahan Baku'],
                'product_id' => $product['id'],
                'quantity' => $qty,
                'type' => 'expense',
                // Cost price sits around 50% of the shelf price.
                'amount' => (int) round($product['price'] * 0.5) * $qty,
                'description' => 'Restock ' . $product['name'] . ' x' . $qty,
                'notes' => 'Produksi batch workshop Kauman',
                'source' => 'manual',
                'payment_method' => 'transfer',
                'merchant_name' => 'Workshop Kauman',
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
        for ($month = 5; $month >= 0; $month--) {
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
                'query' => 'Kampung Batik Kauman, Surakarta',
                'lat' => self::LAT,
                'lng' => self::LNG,
                'radius' => 1.0,
                'score' => 81,
                'competition' => 'tinggi',
                'days_ago' => 5,
                'area' => 'Kecamatan Pasar Kliwon',
                'population' => 76300,
                'competitors' => [
                    ['Batik Cempaka Kauman', 4.5, 288, 'positive', -7.5729, 110.8259],
                    ['Batik Mahkota Solo', 4.6, 402, 'positive', -7.5741, 110.8271],
                    ['Rumah Batik Palupi', 4.3, 176, 'positive', -7.5738, 110.8283],
                    ['Batik Wisnu Kauman', 4.1, 98, 'neutral', -7.5722, 110.8262],
                    ['Griya Batik Sri Rejeki', 3.9, 61, 'neutral', -7.5747, 110.8248],
                ],
            ],
            [
                'query' => 'Beteng Trade Center, Surakarta',
                'lat' => -7.565800,
                'lng' => 110.832100,
                'radius' => 1.0,
                'score' => 69,
                'competition' => 'sangat tinggi',
                'days_ago' => 15,
                'area' => 'Kecamatan Pasar Kliwon',
                'population' => 91200,
                'competitors' => [
                    ['Batik Semar BTC', 4.2, 341, 'positive', -7.5651, 110.8318],
                    ['Grosir Batik Sragen Indah', 3.7, 122, 'neutral', -7.5664, 110.8327],
                    ['Batik Danar Hadi Outlet', 4.6, 876, 'positive', -7.5658, 110.8309],
                    ['Kios Batik Anggun', 3.5, 44, 'negative', -7.5671, 110.8333],
                    ['Batik Puri Kencana', 4.0, 133, 'neutral', -7.5647, 110.8302],
                    ['Batik Sarwo Endah', 3.8, 87, 'neutral', -7.5662, 110.8340],
                ],
            ],
            [
                'query' => 'Pasar Beringharjo, Yogyakarta',
                'lat' => -7.797200,
                'lng' => 110.365400,
                'radius' => 1.5,
                'score' => 62,
                'competition' => 'sangat tinggi',
                'days_ago' => 34,
                'area' => 'Kecamatan Gedongtengen',
                'population' => 48750,
                'competitors' => [
                    ['Batik Keris Beringharjo', 4.5, 712, 'positive', -7.7968, 110.3648],
                    ['Toko Batik Ramai', 3.9, 205, 'neutral', -7.7975, 110.3661],
                    ['Batik Winotosastro', 4.4, 389, 'positive', -7.7959, 110.3652],
                    ['Grosir Batik Jogja Asli', 3.6, 96, 'neutral', -7.7981, 110.3670],
                    ['Batik Terang Bulan', 4.0, 158, 'neutral', -7.7947, 110.3644],
                ],
            ],
            [
                'query' => 'Solo Paragon Mall, Surakarta',
                'lat' => -7.556400,
                'lng' => 110.815700,
                'radius' => 1.0,
                'score' => 74,
                'competition' => 'sedang',
                'days_ago' => 48,
                'area' => 'Kecamatan Banjarsari',
                'population' => 39500,
                'competitors' => [
                    ['Batik Butik Paragon', 4.4, 156, 'positive', -7.5559, 110.8161],
                    ['Sarinah Batik Corner', 4.1, 89, 'neutral', -7.5568, 110.8149],
                    ['Batik Couture Solo', 4.6, 211, 'positive', -7.5551, 110.8172],
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
                    'density' => $item['population'] > 80000 ? 'padat' : 'sedang',
                    'economic_indicator' => $item['score'] >= 74 ? 'menengah-atas' : 'menengah',
                    'dominant_consumer_segment' => $item['query'] === 'Solo Paragon Mall, Surakarta'
                        ? 'kelas_menengah_urban'
                        : 'wisatawan_dan_pekerja',
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
                    'address' => $item['area'] . ', ' . (str_contains($item['query'], 'Yogyakarta') ? 'Yogyakarta' : 'Surakarta'),
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'rating' => $rating,
                    'review_count' => $reviews,
                    'sentiment' => $sentiment,
                    'scraped_data' => json_encode([
                        'price_range' => $rating >= 4.4 ? 'Rp165.000 - Rp3.500.000' : 'Rp75.000 - Rp900.000',
                        'opening_hours' => '08:30 - 21:00',
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
                    'households' => (int) round($item['population'] / 3.7),
                    'density_per_km2' => (int) round($item['population'] / 7.8),
                ]),
                'income_data' => json_encode([
                    'average_monthly_idr' => $item['score'] >= 74 ? 5_400_000 : 3_900_000,
                    'purchasing_power_index' => $item['score'] >= 74 ? 0.76 : 0.60,
                ]),
                'age_distribution' => json_encode([
                    '0-14' => 19.8,
                    '15-24' => 18.5,
                    '25-44' => 33.4,
                    '45-64' => 20.6,
                    '65+' => 7.7,
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
            . ' jiwa. Skor potensi pasar ' . $item['score'] . '/100.';
    }

    private function seedContentAssets(): void
    {
        $contents = [
            [
                'title' => 'Promo Batik Tulis Sida Mukti untuk Musim Nikah',
                'type' => 'social_media',
                'status' => 'published',
                'days_ago' => 6,
                'prompt' => 'Buat caption Instagram untuk Batik Tulis Sida Mukti, target calon pengantin dan keluarga, tone hangat',
                'caption' => "Sida mukti — motif yang dipercaya membawa kemuliaan bagi yang memakainya.\n\nSetiap helai Batik Tulis Sida Mukti kami dikerjakan tangan pembatik Kauman, dari nyanting hingga pewarnaan soga alami. Banyak keluarga memilihnya untuk acara pernikahan adat.\n\nTersedia di Kauman dan Beteng Trade Center.",
                'hashtags' => ['#BatikKusumaKauman', '#BatikTulis', '#KampungBatikKauman', '#BatikSolo', '#SidaMukti'],
                'tone' => 'hangat',
                'style' => 'elegan',
                'platform' => 'instagram',
            ],
            [
                'title' => 'Seragam Batik Kantor — Penawaran Instansi',
                'type' => 'ad_copy',
                'status' => 'published',
                'days_ago' => 12,
                'prompt' => 'Iklan singkat penawaran seragam batik kantor untuk instansi dan perusahaan',
                'caption' => "Seragam batik kantor, motif bisa dikustom, dikirim tepat waktu.\n\nMinimal 20 set, sudah dipercaya sekolah, koperasi, dan kantor kelurahan di Solo Raya. Tim jahit kami siap menyesuaikan identitas instansi Anda.\n\nHubungi 0812-1556-7890 untuk katalog dan sampel kain.",
                'hashtags' => ['#SeragamBatik', '#BatikKantor', '#BatikCustom', '#UMKMSolo'],
                'tone' => 'profesional',
                'style' => 'informatif',
                'platform' => 'facebook',
            ],
            [
                'title' => 'Kilas Balik Lebaran: Sarimbit Keluarga Terlaris',
                'type' => 'social_media',
                'status' => 'published',
                'days_ago' => 20,
                'prompt' => 'Caption reflektif tentang musim Lebaran dan sarimbit keluarga yang laris',
                'caption' => "Terima kasih untuk musim Lebaran yang luar biasa!\n\nKemeja batik pria dan blouse batik wanita jadi favorit keluarga yang ingin tampil serasi di hari raya. Tim pembatik dan penjahit kami lembur demi memastikan semua pesanan sampai tepat waktu.\n\nSampai jumpa di musim liburan berikutnya.",
                'hashtags' => ['#BatikLebaran', '#SarimbitKeluarga', '#BatikKusumaKauman', '#TerimaKasih'],
                'tone' => 'reflektif',
                'style' => 'naratif',
                'platform' => 'instagram',
            ],
            [
                'title' => 'Edukasi: Kenapa Batik Tulis Sutra Lebih Mahal',
                'type' => 'blog_post',
                'status' => 'published',
                'days_ago' => 27,
                'prompt' => 'Artikel edukasi menjelaskan kenapa batik tulis sutra harganya jauh lebih tinggi dari batik cap katun',
                'caption' => "Pelanggan sering bertanya kenapa Batik Tulis Sutra Sekar Kawung kami dibanderol jutaan rupiah, jauh di atas batik cap.\n\nJawabannya ada di bahan dan waktu. Kain sutra ATBM jauh lebih mahal dari katun, dan proses menulis motif kawung dengan canting di atas sutra butuh ketelitian ekstra — sekitar dua bulan pengerjaan untuk satu helai. Bandingkan dengan batik cap yang memakai stempel tembaga dan selesai dalam hitungan hari.\n\nHarga mencerminkan waktu, keahlian, dan bahan — bukan sekadar motif.",
                'hashtags' => ['#EdukasiBatik', '#BatikTulis', '#BatikSutra', '#WarisanBudaya'],
                'tone' => 'edukatif',
                'style' => 'naratif',
                'platform' => null,
            ],
            [
                'title' => 'Koleksi Dress Batik untuk Acara Semi Formal',
                'type' => 'social_media',
                'status' => 'draft',
                'days_ago' => 2,
                'prompt' => 'Caption koleksi dress batik wanita untuk acara semi formal, tone percaya diri',
                'caption' => "Tampil percaya diri di acara semi formal dengan sentuhan wastra.\n\nDress Batik Wanita Pesta kami memadukan potongan modern dengan motif batik khas Kauman — cocok untuk arisan, resepsi, atau acara kantor yang sedikit lebih formal. Ukuran S sampai XL.\n\nCoba langsung di kios Kauman atau tanya ukuran via WhatsApp.",
                'hashtags' => ['#DressBatik', '#BatikModern', '#BatikKusumaKauman', '#OOTDBatik'],
                'tone' => 'percaya diri',
                'style' => 'kasual',
                'platform' => null,
            ],
            [
                'title' => 'Behind the Process: Menulis Motif Kawung',
                'type' => 'social_media',
                'status' => 'draft',
                'days_ago' => 3,
                'prompt' => 'Caption dokumentasi proses menulis motif kawung oleh pembatik senior',
                'caption' => "Pak Slamet sudah 28 tahun memegang canting.\n\nMotif kawung yang terlihat sederhana ternyata butuh presisi tinggi — setiap lingkaran harus sejajar tanpa bantuan pola cetak. Satu kain sutra premium bisa memakan waktu dua bulan penuh di tangannya.\n\nInilah yang Anda bawa pulang setiap kali memilih batik tulis asli.",
                'hashtags' => ['#BehindTheProcess', '#Pembatik', '#KampungBatikKauman', '#MotifKawung'],
                'tone' => 'reflektif',
                'style' => 'dokumenter',
                'platform' => null,
            ],
            [
                'title' => 'Undangan Pameran Kriya Nusantara Solo',
                'type' => 'email',
                'status' => 'draft',
                'days_ago' => 1,
                'prompt' => 'Email undangan pelanggan untuk mengunjungi booth pameran kriya di Solo',
                'caption' => "Kami akan hadir di Pameran Kriya Nusantara, Diamond Convention Center Solo.\n\nBooth B-22, membawa koleksi batik tulis sutra premium dan dress batik pesta yang belum pernah kami pajang di kios. Ada demo menulis motif kawung setiap sore.\n\nSampai jumpa di sana.",
                'hashtags' => ['#PameranKriya', '#BatikKusumaKauman', '#SoloEvent'],
                'tone' => 'formal',
                'style' => 'undangan',
                'platform' => null,
            ],
            [
                'title' => 'Newsletter: Laporan Ekspansi Kios Beringharjo',
                'type' => 'email',
                'status' => 'archived',
                'days_ago' => 95,
                'prompt' => 'Email pengumuman pembukaan kios baru di Pasar Beringharjo Yogyakarta',
                'caption' => "Kabar baik — kios kedua kami resmi buka di Pasar Beringharjo, Yogyakarta!\n\nSetelah bertahun-tahun hanya melayani Solo Raya, kini pelanggan di Yogyakarta bisa mampir langsung untuk melihat koleksi lengkap kami, dari batik cap harian hingga tulis sutra premium.\n\nTerima kasih atas dukungan yang membuat langkah ini mungkin.",
                'hashtags' => ['#EkspansiKios', '#BatikKusumaKauman', '#PasarBeringharjo'],
                'tone' => 'bangga',
                'style' => 'pengumuman',
                'platform' => null,
            ],
            [
                'title' => 'Promo Gantungan Kunci Batik untuk Oleh-oleh',
                'type' => 'social_media',
                'status' => 'archived',
                'days_ago' => 60,
                'prompt' => 'Caption promosi gantungan kunci batik sebagai oleh-oleh murah meriah',
                'caption' => "Cari oleh-oleh khas Solo yang ringan di kantong?\n\nGantungan Kunci Batik kami dibuat dari sisa kain produksi — ramah lingkungan dan tetap cantik. Cocok dibagikan ke rekan kerja atau teman sepulang dari Kauman.\n\nTersedia di semua kios kami.",
                'hashtags' => ['#OlehOlehSolo', '#GantunganKunciBatik', '#BatikKusumaKauman'],
                'tone' => 'ceria',
                'style' => 'kasual',
                'platform' => 'instagram',
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
                        'permalink' => 'https://' . $item['platform'] . '.com/batikkusumakauman',
                        'reach' => random_int(1200, 6800),
                        'engagement' => random_int(90, 620),
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
            ['sri.kasir@batikkusuma.id', 'staff', 'accepted', 210],
            ['bambang.produksi@batikkusuma.id', 'staff', 'accepted', 180],
            ['dewi.beringharjo@batikkusuma.id', 'staff', 'accepted', 55],
            ['akuntan@konsultanpajaksolo.id', 'viewer', 'accepted', 90],
            ['calon.mitra@batikkusuma.id', 'staff', 'pending', 3],
            ['magang.uns@batikkusuma.id', 'viewer', 'pending', 6],
            ['audit.lama@koperasikauman.id', 'viewer', 'expired', 140],
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

    /**
     * Registers the owner's membership up front so the SiPromo AI pipeline
     * (which verifies tenant access against this table) works immediately,
     * without waiting on the bridge's lazy just-in-time insert.
     */
    private function seedMembership(): void
    {
        DB::table('umkm_memberships')->insert([
            'id' => (string) Str::uuid(),
            'umkm_id' => $this->umkmId,
            'user_id' => $this->userId,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now()->subMonths(14),
        ]);
    }
}
