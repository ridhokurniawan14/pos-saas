<?php

namespace App\Support;

use App\Models\Order;

class ReceiptPayload
{
    public static function make(Order $o, $business): array
    {
        $o->loadMissing(['items', 'payments', 'outlet', 'cashier', 'customer', 'voider']);
        $custom = $business->canUse('custom_receipt');
        $loyalty = $business->canUse('loyalty') && $business->loyalty_enabled;
        $tz = $business->timezone;

        return [
            'business' => $business->name,
            'logo_url' => $custom && $business->receipt_logo_path ? '/storage/' . $business->receipt_logo_path : null,
            'cashier' => $o->cashier?->name,
            'customer' => $o->customer?->name,
            'outlet' => [
                'name' => $o->outlet->name,
                'address' => $o->outlet->address,
                'phone' => $o->outlet->phone,
                'header' => $custom ? $o->outlet->receipt_header : null,
                'footer' => $custom ? $o->outlet->receipt_footer : null,
            ],
            'order' => [
                'id' => $o->id,
                'number' => $o->order_number,
                'status' => $o->status,
                'void_reason' => $o->void_reason,
                'voided_at' => $o->voided_at?->timezone($tz)->format('d/m/Y H:i'),
                'voided_by' => $o->voider?->name,
                'date' => $o->created_at->timezone($tz)->format('d/m/Y H:i'),
                'items' => $o->items->map(fn($i) => [
                    'name' => $i->name_snapshot,
                    'unit' => $i->unit_name,
                    'qty' => $i->qty,
                    'price' => $i->price_snapshot,
                    'subtotal' => $i->subtotal,
                ])->values(),
                'subtotal' => $o->subtotal,
                'discount' => $o->discount,
                'points_used' => $o->points_used,
                'points_discount' => $o->points_discount,
                'points_earned' => $o->points_earned,
                'points_balance' => $loyalty ? $o->customer?->points : null,
                'total' => $o->total,
                'paid' => $o->paid,
                'change' => $o->change_amount,
                'due' => $o->due_amount,
                'payments' => $o->payments->map(fn($p) => [
                    'method' => $p->method,
                    'amount' => $p->amount,
                    'ref' => $p->ref,
                ])->values(),
            ],
        ];
    }
}
