<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\UmkmProfile;
use App\Services\Report\ExportService;
use App\Services\Report\ReportAggregationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private ReportAggregationService $reportService,
        private ExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $period = $request->query('period', 'monthly');
        $reportData = $this->reportService->aggregate($profile->id, $period);

        return view('sikas.reports', [
            'activeNav' => 'sikas',
            'profile' => $profile,
            'period' => $period,
            'reportData' => $reportData,
        ]);
    }

    public function export(Request $request): Response
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $csv = $this->exportService->exportCsv($profile->id);
        $filename = 'laporan-keuangan-' . date('Y-m-d') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
