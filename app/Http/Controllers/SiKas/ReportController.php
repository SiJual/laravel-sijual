<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\UmkmProfile;
use App\Services\Report\ExportService;
use App\Services\Report\ReportAggregationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private ReportAggregationService $reportService,
        private ExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $period = $request->query('period', 'monthly');
        $startDate = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->filled('end_date') ? \Carbon\Carbon::parse($request->end_date) : null;

        $reportData = $this->reportService->aggregate($profile->id, $period, $startDate, $endDate);

        return view('sikas.reports', [
            'activeNav' => 'sikas',
            'profile' => $profile,
            'period' => $period,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'reportData' => $reportData,
        ]);
    }

    public function export(Request $request)
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $format = $request->input('format', 'csv');
        $period = $request->input('period', 'monthly');
        $startDate = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date) : null;
        $endDate = $request->filled('end_date') ? \Carbon\Carbon::parse($request->end_date) : null;

        if ($format === 'pdf') {
            return $this->exportPdf($profile, $period, $startDate, $endDate);
        }

        $csv = $this->exportService->exportCsv($profile->id);
        $filename = 'laporan-keuangan-' . date('Y-m-d') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportPdf($profile, string $period, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        $reportData = $this->reportService->aggregate($profile->id, $period, $startDate, $endDate);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('sikas.report-pdf', [
            'profile' => $profile,
            'reportData' => $reportData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => now()->format('d M Y, H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('laporan-keuangan-' . $profile->id . '-' . date('Y-m-d') . '.pdf');
    }
}
