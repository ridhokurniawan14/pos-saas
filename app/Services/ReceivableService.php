<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ReceivablePayment;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceivableService
{
    /** Bayar piutang: dialokasikan ke struk bon paling lama lebih dulu. */
    public function pay(Customer $customer, int $amount, string $method, ?Shift $shift, ?string $note): void
    {
        DB::transaction(function () use ($customer, $amount, $method, $shift, $note) {
            Customer::whereKey($customer->id)->lockForUpdate()->first(); // serialisasi per pelanggan

            $orders = Order::where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->where('due_amount', '>', 0)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $repaid = ReceivablePayment::where('customer_id', $customer->id)
                ->whereNull('reversed_at')
                ->selectRaw('order_id, SUM(amount) as s')
                ->groupBy('order_id')
                ->pluck('s', 'order_id');

            $owed = fn($o) => max(0, $o->due_amount - (int) ($repaid[$o->id] ?? 0));
            $outstanding = (int) $orders->sum($owed);

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah melebihi sisa piutang pelanggan (Rp ' . number_format($outstanding, 0, ',', '.') . ').',
                ]);
            }

            $batch = (string) Str::uuid();
            $left = $amount;

            foreach ($orders as $o) {
                if ($left <= 0) {
                    break;
                }
                $remaining = $owed($o);
                if ($remaining <= 0) {
                    continue;
                }

                $take = min($remaining, $left);

                ReceivablePayment::create([
                    'customer_id' => $customer->id,
                    'order_id' => $o->id,
                    'shift_id' => $shift?->id,
                    'batch_id' => $batch,
                    'method' => $method,
                    'amount' => $take,
                    'note' => $note,
                    'created_by' => auth()->id(),
                ]);

                $left -= $take;
            }
        });
    }
}
