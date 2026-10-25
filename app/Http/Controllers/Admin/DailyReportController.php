<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DailyReportExport;
use App\Http\Controllers\Controller;
use App\Services\DailyReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class DailyReportController extends Controller
{
    protected DailyReportService $reportService;

    public function __construct(DailyReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Show the daily delivery & loading report page.
     */
    public function index()
    {
        return view('admin.daily_reports.index', $this->reportService->getFilterOptions());
    }

    /**
     * Return the report data as JSON for the page's AJAX requests.
     */
    public function data(Request $request)
    {
        return response()->json($this->filteredPayload($request));
    }

    /**
     * Export the daily report as an Excel workbook (orders + delivery representatives).
     */
    public function exportExcel(Request $request)
    {
        $payload = $this->filteredPayload($request);

        return Excel::download(
            new DailyReportExport($payload, $this->currency()),
            'daily-report-' . $payload['filters']['date'] . '.xlsx'
        );
    }

    /**
     * Export the daily report as a PDF document (orders + delivery representatives).
     */
    public function exportPdf(Request $request)
    {
        $payload = $this->filteredPayload($request);

        $pdf = PDF::loadView('admin.daily_reports.pdf', [
            'orders'   => $payload['orders'],
            'drivers'  => $payload['drivers'],
            'summary'  => $payload['summary'],
            'filters'  => $payload['filters'],
            'currency' => $this->currency(),
        ], [], [
            'format'      => 'A4',
            'orientation' => 'portrait',
        ]);

        return $pdf->download('daily-report-' . $payload['filters']['date'] . '.pdf');
    }

    /**
     * Validate the report filters and build the shared report payload once.
     */
    private function filteredPayload(Request $request): array
    {
        $request->validate([
            'date'        => ['nullable', 'date'],
            'period_id'   => ['nullable', 'integer', 'exists:delivery_periods,id'],
            'delivery_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return $this->reportService->getData(
            $request->input('date'),
            $request->integer('period_id') ?: null,
            $request->integer('delivery_id') ?: null
        );
    }

    private function currency(): string
    {
        return (string) (data_get(Cache::get('settings'), 'default_currency') ?: 'ر.س');
    }
}
