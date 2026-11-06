<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $svc)
    {
        [$business, $from, $to, $outletId, $advanced] = $this->prepare($request);

        return Inertia::render('reports/Index', [
            'report' => $svc->sales($from, $to, $outletId, null, $advanced),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'outlet' => $outletId],
            'outlets' => $business->outlets()->orderBy('created_at')->get(['id', 'name']),
            'advanced' => $advanced,
        ]);
    }

    public function export(Request $request, ReportService $svc)
    {
        [$business, $from, $to, $outletId, $advanced] = $this->prepare($request);
        abort_unless($advanced, 403, 'Ekspor laporan tersedia mulai paket Tumbuh.');

        $r = $svc->sales($from, $to, $outletId, null, true);
        $s = $r['summary'];

        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $w = new Writer();
        $w->openToFile($path);

        $w->getCurrentSheet()->setName('Ringkasan');
        $w->addRow(Row::fromValues(['Laporan penjualan', $business->name]));
        $w->addRow(Row::fromValues(['Periode', $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y')]));
        $w->addRow(Row::fromValues([]));
        foreach (
            [
                ['Jumlah transaksi', $s['orders']],
                ['Penjualan kotor', $s['gross']],
                ['Diskon dan poin', $s['discount']],
                ['Omzet', $s['revenue']],
                ['HPP', $s['cogs']],
                ['Laba kotor', $s['gross_profit']],
                ['Pengeluaran', $s['expenses']],
                ['Laba bersih', $s['net_profit']],
                ['Margin kotor (%)', $s['margin']],
                ['Belum dibayar (bon)', $s['due']],
            ] as $line
        ) {
            $w->addRow(Row::fromValues($line));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Harian');
        $w->addRow(Row::fromValues(['Tanggal', 'Transaksi', 'Omzet', 'HPP', 'Laba kotor', 'Pengeluaran', 'Laba bersih']));
        foreach ($r['days'] as $d) {
            $w->addRow(Row::fromValues([$d['date'], $d['orders'], $d['revenue'], $d['cogs'], $d['gross_profit'], $d['expenses'], $d['net_profit']]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Produk Terlaris');
        $w->addRow(Row::fromValues(['Produk', 'Terjual (satuan dasar)', 'Satuan', 'Pendapatan', 'Laba kotor']));
        foreach ($r['top'] as $p) {
            $w->addRow(Row::fromValues([$p['name'], $p['qty'], $p['unit'], $p['revenue'], $p['profit']]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Metode Bayar');
        $w->addRow(Row::fromValues(['Metode', 'Jumlah']));
        foreach ($r['methods'] as $m) {
            $w->addRow(Row::fromValues([$m['method'], $m['amount']]));
        }

        $w->addNewSheetAndMakeItCurrent()->setName('Pengeluaran');
        $w->addRow(Row::fromValues(['Kategori', 'Jumlah']));
        foreach ($r['expense_by_category'] as $e) {
            $w->addRow(Row::fromValues([$e['name'], $e['amount']]));
        }

        $w->close();

        return response()->download($path, 'laporan-' . $from->toDateString() . '-sd-' . $to->toDateString() . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function prepare(Request $request): array
    {
        $business = $request->attributes->get('business');
        abort_unless($business->isManager($request->user()), 403, 'Laporan hanya untuk owner/admin.');

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'outlet' => ['nullable', 'string'],
        ]);

        $from = Date::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Date::parse($request->input('to', now()->toDateString()))->endOfDay();

        if ($to->lt($from)) {
            $to = $from->copy()->endOfDay();
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        $outletId = $request->outlet
            ? $business->outlets()->where('id', $request->outlet)->value('id')
            : null;

        return [$business, $from, $to, $outletId, $business->canUse('advanced_report')];
    }
}
