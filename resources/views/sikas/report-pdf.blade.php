<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan — {{ $profile->business_name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        
        .header { padding: 28px 40px; border-bottom: 3px solid #9D3D2B; }
        .header-title { font-size: 22px; font-weight: 700; color: #9D3D2B; margin-bottom: 4px; }
        .header-sub { font-size: 12px; color: #666; }
        
        .content { padding: 28px 40px; }
        
        .meta-block { background: #FBF7F6; border: 1px solid #F2E8E5; border-radius: 8px; padding: 16px; margin-bottom: 24px; }
        .meta-block table { width: 100%; border-collapse: collapse; }
        .meta-block td { padding: 4px 0; color: #555; }
        .meta-block td:first-child { font-weight: 700; color: #333; width: 40%; }
        
        .section-title { font-size: 14px; font-weight: 700; color: #1a1a1a; margin: 20px 0 10px; border-bottom: 1px solid #e8e0dc; padding-bottom: 6px; }
        
        .stat-grid { display: table; width: 100%; margin-bottom: 24px; }
        .stat-card { display: table-cell; width: 33.33%; background: #F8F5F4; border: 1px solid #EAE0DC; border-radius: 6px; padding: 14px; text-align: center; }
        .stat-card + .stat-card { margin-left: 10px; }
        .stat-label { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .stat-value { font-size: 16px; font-weight: 700; color: #1a1a1a; }
        .stat-value.income { color: #4E8057; }
        .stat-value.expense { color: #9D3D2B; }
        .stat-value.balance-pos { color: #4E8057; }
        .stat-value.balance-neg { color: #9D3D2B; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 12px 40px; border-top: 1px solid #e8e0dc; font-size: 10px; color: #888; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">SiJual — Laporan Keuangan</div>
        <div class="header-sub">{{ $profile->business_name }} &middot; Dibuat: {{ $generatedAt }}</div>
    </div>

    <div class="content">
        <div class="meta-block">
            <table>
                <tr>
                    <td>Nama Usaha</td>
                    <td>{{ $profile->business_name }}</td>
                </tr>
                <tr>
                    <td>Periode Laporan</td>
                    <td>{{ ucfirst($period) }}</td>
                </tr>
                <tr>
                    <td>Alamat</td>
                    <td>{{ $profile->address ?? '-' }}, {{ $profile->city ?? '' }}</td>
                </tr>
            </table>
        </div>

        <div class="section-title">Ringkasan Keuangan</div>

        <table width="100%" style="border-collapse: collapse; margin-bottom: 24px;">
            <tr>
                <td style="padding: 10px; background: #F0F6F1; border: 1px solid #d4e6d8; text-align: center; border-radius: 4px;">
                    <div style="font-size:10px; font-weight:700; color:#4E8057; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Total Pemasukan</div>
                    <div style="font-size:16px; font-weight:700; color:#4E8057;">Rp {{ number_format($reportData['total_income'], 0, ',', '.') }}</div>
                </td>
                <td style="width:16px;"></td>
                <td style="padding: 10px; background: #FAF0EE; border: 1px solid #e8cdc8; text-align: center; border-radius: 4px;">
                    <div style="font-size:10px; font-weight:700; color:#9D3D2B; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Total Pengeluaran</div>
                    <div style="font-size:16px; font-weight:700; color:#9D3D2B;">Rp {{ number_format($reportData['total_expense'], 0, ',', '.') }}</div>
                </td>
                <td style="width:16px;"></td>
                <td style="padding: 10px; background: #F8F5F4; border: 1px solid #e0d8d4; text-align: center; border-radius: 4px;">
                    <div style="font-size:10px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Keuntungan Bersih</div>
                    <div style="font-size:16px; font-weight:700; color:{{ $reportData['net_profit'] >= 0 ? '#4E8057' : '#9D3D2B' }};">Rp {{ number_format($reportData['net_profit'], 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        @if(!empty($reportData['transactions']))
            <div class="section-title">Rincian Transaksi</div>
            <table width="100%" style="border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="background: #9D3D2B; color: white;">
                        <th style="padding: 8px 10px; text-align: left; border-radius: 4px 0 0 0;">Tanggal</th>
                        <th style="padding: 8px 10px; text-align: left;">Deskripsi</th>
                        <th style="padding: 8px 10px; text-align: left;">Kategori</th>
                        <th style="padding: 8px 10px; text-align: center;">Tipe</th>
                        <th style="padding: 8px 10px; text-align: right; border-radius: 0 4px 0 0;">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['transactions'] as $i => $tx)
                    <tr style="{{ $i % 2 === 0 ? 'background: #FAFAFA;' : 'background: white;' }}">
                        <td style="padding: 7px 10px; border-bottom: 1px solid #EEE;">{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d/m/Y') }}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #EEE;">{{ $tx->description ?? '-' }}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #EEE;">{{ $tx->category->name ?? '-' }}</td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #EEE; text-align: center;">
                            <span style="padding: 2px 8px; border-radius: 100px; font-size: 9px; font-weight: 700; text-transform: uppercase; background: {{ $tx->type === 'income' ? '#E0EFE4' : '#FDECE9' }}; color: {{ $tx->type === 'income' ? '#4E8057' : '#9D3D2B' }};">
                                {{ $tx->type === 'income' ? 'Masuk' : 'Keluar' }}
                            </span>
                        </td>
                        <td style="padding: 7px 10px; border-bottom: 1px solid #EEE; text-align: right; font-weight: 600; color: {{ $tx->type === 'income' ? '#4E8057' : '#9D3D2B' }};">
                            {{ $tx->type === 'income' ? '+' : '-' }} Rp {{ number_format($tx->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="footer">
        <span>SiJual — Platform Manajemen UMKM Cerdas</span>
        <span>Laporan dihasilkan: {{ $generatedAt }}</span>
    </div>
</body>
</html>
