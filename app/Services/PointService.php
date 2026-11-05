<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PointMovement;
use Illuminate\Validation\ValidationException;

class PointService
{
    /** Panggil di dalam DB::transaction. Mengunci baris pelanggan supaya saldo tidak balapan. */
    public function adjust(string $customerId, int $points, string $type, ?string $orderId = null, ?string $note = null, bool $allowNegative = true): PointMovement
    {
        $c = Customer::withTrashed()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if (! $allowNegative && $c->points + $points < 0) {
            throw ValidationException::withMessages(['points' => 'Poin pelanggan tidak cukup.']);
        }

        $c->points += $points;
        $c->save();

        return PointMovement::create([
            'customer_id' => $c->id,
            'order_id' => $orderId,
            'type' => $type,
            'points' => $points,
            'balance_after' => $c->points,
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }
}
