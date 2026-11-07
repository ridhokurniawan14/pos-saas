<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $svc)
    {
        [$business, $from, $to, $outletId, $advanced, $capped] = $this->prepare($request);

        return Inertia::render('reports/Index', [
            'report' => $svc->sales($from, $to, $outletId, null, $advanced),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'outlet' => $outletId],
            'outlets' => $business->outlets()->orderBy('created_at')->get(['id', 'name']),
            'advanced' => $advanced,
            'capped' => $capped,
        ]);
    }

    public function export(Request $request, ReportService $svc)
    {
        [$business, $from, $to, $outletId, $advanced] = $this->prepare($request);
        abort_unless($advanced, 403, 'Ekspor laporan tersedia mulai paket Tumbuh.');

        $r = $svc->sales($from, $to, $outletId, null, true);
        $s = $r['summary'];
        $outletName = $outletId
            ? $business->outlets()->where('id', $outletId)->value('name')
            : ($business->outlets()->count() === 1 ? $business->outlets()->value('name') : 'Semua cabang');
        $methodLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit', 'transfer' => 'Transfer'];

        // ---- gaya ----
        $edge = 'BFBFBF';
        $part = fn(BorderName $n) => new BorderPart($n, $edge, BorderWidth::THIN, BorderStyle::SOLID);
        $border = new Border($part(BorderName::TOP), $part(BorderName::BOTTOM), $part(BorderName::LEFT), $part(BorderName::RIGHT));

        $base = (new Style())->withBorder($border);
        $st = [
            'title' => (new Style())->withFontBold(true)->withFontSize(14),
            'sub' => (new Style())->withFontColor('6B7280'),
            'head' => $base->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('1F2937')
                ->withCellAlignment(CellAlignment::CENTER),
            'text' => $base,
            'num' => $base->withFormat('#,##0')->withCellAlignment(CellAlignment::RIGHT),
            'dec' => $base->withFormat('0.0')->withCellAlignment(CellAlignment::RIGHT),
            'neg' => $base->withFormat('#,##0')->withCellAlignment(CellAlignment::RIGHT)->withFontColor('DC2626'),
            'tText' => $base->withFontBold(true)->withBackgroundColor('F3F4F6'),
            'tNum' => $base->withFontBold(true)->withBackgroundColor('F3F4F6')
                ->withFormat('#,##0')->withCellAlignment(CellAlignment::RIGHT),
            'tNeg' => $base->withFontBold(true)->withBackgroundColor('F3F4F6')
                ->withFormat('#,##0')->withCellAlignment(CellAlignment::RIGHT)->withFontColor('DC2626'),
        ];

        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $w = new Writer();
        $w->openToFile($path);

        // ---- pembantu ----
        $add = fn(array $cells) => $w->addRow(new Row(array_map(fn($c) => Cell::fromValue($c[0], $c[1] ?? null), $cells)));
        $head = fn(array $labels) => $add(array_map(fn($l) => [$l, $st['head']], $labels));
        $t = fn($v, bool $bold = false) => [$v, $bold ? $st['tText'] : $st['text']];
        $n = fn($v, bool $bold = false) => [$v, $v < 0 ? ($bold ? $st['tNeg'] : $st['neg']) : ($bold ? $st['tNum'] : $st['num'])];
        $blank = fn() => $w->addRow(Row::fromValues([]));
        $sum = fn(array $rows, string $key) => (int) array_sum(array_column($rows, $key));

        // buka sheet baru: nama, lebar kolom, lalu judul laporan
        $sheet = function (string $sheetName, string $title, float $firstWidth, float $otherWidth, int $lastCol, bool $first = false)
        use ($w, $add, $st, $business, $outletName, $from, $to, $blank) {
            $sh = $first ? $w->getCurrentSheet() : $w->addNewSheetAndMakeItCurrent();
            $sh->setName($sheetName);
            $sh->setColumnWidth($firstWidth, 1);
            if ($lastCol > 1) {
                $sh->setColumnWidthForRange($otherWidth, 2, $lastCol);
            }
            $add([[$title, $st['title']]]);
            $add([[$business->name . ' | ' . $outletName, $st['sub']]]);
            $add([['Periode ' . $from->format('d/m/Y') . ' sampai ' . $to->format('d/m/Y'), $st['sub']]]);
            $blank();
        };

        // ---- sheet 1: Ringkasan ----
        $sheet('Ringkasan', 'Laporan Penjualan', 34, 20, 2, true);
        $head(['Keterangan', 'Nilai']);
        $add([$t('Jumlah transaksi'), $n($s['orders'])]);
        $add([$t('Penjualan kotor (Rp)'), $n($s['gross'])]);
        $add([$t('Diskon dan poin (Rp)'), $n($s['discount'])]);
        $add([$t('Omzet (Rp)', true), $n($s['revenue'], true)]);
        $add([$t('HPP / modal terjual (Rp)'), $n($s['cogs'])]);
        $add([$t('Laba kotor (Rp)', true), $n($s['gross_profit'], true)]);
        $add([$t('Pengeluaran (Rp)'), $n($s['expenses'])]);
        $add([$t('Laba bersih (Rp)', true), $n($s['net_profit'], true)]);
        $add([$t('Margin kotor (%)'), [$s['margin'], $st['dec']]]);
        $add([$t('Bon belum dibayar (Rp)'), $n($s['due'])]);

        // ---- sheet 2: Harian ----
        $sheet('Harian', 'Rincian Per Hari', 16, 18, 7);
        $head(['Tanggal', 'Transaksi', 'Omzet (Rp)', 'HPP (Rp)', 'Laba kotor (Rp)', 'Pengeluaran (Rp)', 'Laba bersih (Rp)']);
        foreach ($r['days'] as $d) {
            $add([
                $t(\Carbon\Carbon::parse($d['date'])->format('d/m/Y')),
                $n($d['orders']),
                $n($d['revenue']),
                $n($d['cogs']),
                $n($d['gross_profit']),
                $n($d['expenses']),
                $n($d['net_profit']),
            ]);
        }
        $add([
            $t('TOTAL', true),
            $n($sum($r['days'], 'orders'), true),
            $n($sum($r['days'], 'revenue'), true),
            $n($sum($r['days'], 'cogs'), true),
            $n($sum($r['days'], 'gross_profit'), true),
            $n($sum($r['days'], 'expenses'), true),
            $n($sum($r['days'], 'net_profit'), true),
        ]);

        // ---- sheet 3: Produk terlaris ----
        $sheet('Produk Terlaris', 'Produk Terlaris (berdasarkan pendapatan)', 36, 18, 5);
        $head(['Produk', 'Terjual', 'Satuan', 'Pendapatan (Rp)', 'Laba kotor (Rp)']);
        foreach ($r['top'] as $p) {
            $add([$t($p['name']), $n($p['qty']), $t($p['unit']), $n($p['revenue']), $n($p['profit'])]);
        }
        if ($r['top']) {
            $add([
                $t('TOTAL', true),
                $t('', true),
                $t('', true),
                $n($sum($r['top'], 'revenue'), true),
                $n($sum($r['top'], 'profit'), true),
            ]);
        }

        // ---- sheet 4: Metode bayar ----
        $sheet('Metode Bayar', 'Metode Pembayaran', 30, 20, 2);
        $head(['Metode', 'Jumlah (Rp)']);
        foreach ($r['methods'] as $m) {
            $add([$t($methodLabel[$m['method']] ?? $m['method']), $n($m['amount'])]);
        }
        if ($r['methods']) {
            $add([$t('TOTAL', true), $n($sum($r['methods'], 'amount'), true)]);
        }

        // ---- sheet 5: Pengeluaran ----
        $sheet('Pengeluaran', 'Pengeluaran Per Kategori', 30, 20, 2);
        $head(['Kategori', 'Jumlah (Rp)']);
        foreach ($r['expense_by_category'] as $e) {
            $add([$t($e['name']), $n($e['amount'])]);
        }
        if ($r['expense_by_category']) {
            $add([$t('TOTAL', true), $n($sum($r['expense_by_category'], 'amount'), true)]);
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

        $capped = false;
        if ($from->diffInDays($to) >= 366) {
            $from = $to->copy()->subDays(365)->startOfDay();
            $capped = true;
        }

        $outletId = $request->outlet
            ? $business->outlets()->where('id', $request->outlet)->value('id')
            : null;

        return [$business, $from, $to, $outletId, $business->canUse('advanced_report'), $capped];
    }
}
