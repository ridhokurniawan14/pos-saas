<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Carbon\CarbonInterface;

class ReportService
{
    public function sales(CarbonInterface $start, CarbonInterface $end, ?string $outletId = null, ?string $cashierId = null, bool $advanced = false): array
    {
        $orders = fn() => Order::query()
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->when($outletId, fn($q) => $q->where('orders.outlet_id', $outletId))
            ->when($cashierId, fn($q) => $q->where('orders.cashier_id', $cashierId));

        $items = fn() => OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->when($outletId, fn($q) => $q->where('orders.outlet_id', $outletId))
            ->when($cashierId, fn($q) => $q->where('orders.cashier_id', $cashierId));

        $t = $orders()->selectRaw(
            'COUNT(*) as c, COALESCE(SUM(orders.total),0) as total, COALESCE(SUM(orders.subtotal),0) as gross, '
                . 'COALESCE(SUM(orders.discount + orders.points_discount),0) as disc, COALESCE(SUM(orders.due_amount),0) as due, '
                . 'COALESCE(SUM(orders.change_amount),0) as chg'
        )->first();

        $count = (int) $t->c;
        $revenue = (int) $t->total;

        $key = fn($r) => substr((string) $r->d, 0, 10);

        $daily = $orders()
            ->selectRaw('orders.created_at::date as d, COUNT(*) as c, SUM(orders.total) as t')
            ->groupByRaw('orders.created_at::date')
            ->get()->keyBy($key);

        $methods = OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$start, $end])
            ->when($outletId, fn($q) => $q->where('orders.outlet_id', $outletId))
            ->when($cashierId, fn($q) => $q->where('orders.cashier_id', $cashierId))
            ->selectRaw('order_payments.method as m, SUM(order_payments.amount) as t')
            ->groupBy('order_payments.method')
            ->get()
            ->map(fn($r) => ['method' => $r->m, 'amount' => (int) $r->t - ($r->m === 'cash' ? (int) $t->chg : 0)])
            ->values()->all();

        $summary = [
            'orders' => $count,
            'revenue' => $revenue,
            'gross' => (int) $t->gross,
            'discount' => (int) $t->disc,
            'due' => (int) $t->due,
            'avg' => $count ? intdiv($revenue, $count) : 0,
        ];

        $cogsDaily = collect();
        $expDaily = collect();
        $top = [];
        $expenseByCategory = [];

        if ($advanced) {
            $cogs = (int) $items()->selectRaw('COALESCE(SUM(order_items.cost_snapshot * order_items.qty),0) as c')->first()->c;

            $cogsDaily = $items()
                ->selectRaw('orders.created_at::date as d, SUM(order_items.cost_snapshot * order_items.qty) as c')
                ->groupByRaw('orders.created_at::date')
                ->get()->keyBy($key);

            $exp = fn() => Expense::query()
                ->whereBetween('expenses.expense_date', [$start->toDateString(), $end->toDateString()])
                ->when($outletId, fn($q) => $q->where('expenses.outlet_id', $outletId));

            $expenses = (int) $exp()->sum('expenses.amount');

            $expDaily = $exp()
                ->selectRaw('expenses.expense_date as d, SUM(expenses.amount) as t')
                ->groupBy('expenses.expense_date')
                ->get()->keyBy($key);

            $expenseByCategory = $exp()
                ->selectRaw('expenses.category as name, SUM(expenses.amount) as t')
                ->groupBy('expenses.category')
                ->orderByDesc('t')
                ->get()
                ->map(fn($r) => ['name' => $r->name, 'amount' => (int) $r->t])
                ->values()->all();

            $top = $items()
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->selectRaw(
                    "order_items.name_snapshot as name, COALESCE(products.unit, '') as unit, "
                        . 'SUM(order_items.qty * order_items.conversion) as qty, SUM(order_items.subtotal) as revenue, '
                        . 'SUM(order_items.subtotal - order_items.cost_snapshot * order_items.qty) as profit'
                )
                ->groupByRaw('order_items.product_id, order_items.name_snapshot, products.unit')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(fn($r) => [
                    'name' => $r->name,
                    'unit' => $r->unit,
                    'qty' => (int) $r->qty,
                    'revenue' => (int) $r->revenue,
                    'profit' => (int) $r->profit,
                ])->values()->all();

            $summary += [
                'cogs' => $cogs,
                'gross_profit' => $revenue - $cogs,
                'expenses' => $expenses,
                'net_profit' => $revenue - $cogs - $expenses,
                'margin' => $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : 0,
            ];
        }

        $days = [];
        for ($d = $start->copy()->startOfDay(); $d->lte($end); $d = $d->addDay()) {
            $k = $d->toDateString();
            $rev = (int) ($daily->get($k)?->t ?? 0);

            $row = [
                'date' => $k,
                'label' => $d->format('d/m'),
                'orders' => (int) ($daily->get($k)?->c ?? 0),
                'revenue' => $rev,
            ];

            if ($advanced) {
                $c = (int) ($cogsDaily->get($k)?->c ?? 0);
                $e = (int) ($expDaily->get($k)?->t ?? 0);
                $row += ['cogs' => $c, 'gross_profit' => $rev - $c, 'expenses' => $e, 'net_profit' => $rev - $c - $e];
            }

            $days[] = $row;
        }

        return [
            'summary' => $summary,
            'days' => $days,
            'methods' => $methods,
            'top' => $top,
            'expense_by_category' => $expenseByCategory,
        ];
    }
}
