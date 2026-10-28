<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasUuids, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
            'expected_cash' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
    public function cashMovements()
    {
        return $this->hasMany(CashMovement::class);
    }

    /** Ringkasan uang dan penjualan selama shift (dihitung dari transaksi). */
    public function summary(): array
    {
        $done = Order::where('shift_id', $this->id)->where('status', 'completed');

        $methods = OrderPayment::whereIn('order_id', (clone $done)->select('id'))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn($v) => (int) $v)
            ->all();

        $change = (int) (clone $done)->sum('change_amount');
        $cashSales = ($methods['cash'] ?? 0) - $change; // tunai diterima dikurangi kembalian
        $in = (int) $this->cashMovements()->where('type', 'in')->sum('amount');
        $out = (int) $this->cashMovements()->where('type', 'out')->sum('amount');

        return [
            'orders_count' => (clone $done)->count(),
            'sales_total' => (int) (clone $done)->sum('total'),
            'voided_count' => Order::where('shift_id', $this->id)->where('status', 'void')->count(),
            'methods' => $methods,
            'cash_sales' => $cashSales,
            'cash_in' => $in,
            'cash_out' => $out,
            'expected_cash' => $this->opening_cash + $cashSales + $in - $out,
        ];
    }

    public function payload(string $tz): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'outlet_name' => $this->outlet?->name,
            'opened_at' => $this->opened_at->timezone($tz)->format('d/m/Y H:i'),
            'opening_cash' => $this->opening_cash,
            ...$this->summary(),
            'movements' => $this->cashMovements()->latest('created_at')->get()->map(fn($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'amount' => $m->amount,
                'reason' => $m->reason,
                'time' => $m->created_at->timezone($tz)->format('H:i'),
            ])->values(),
        ];
    }
}
