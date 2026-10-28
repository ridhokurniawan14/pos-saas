<?php

namespace App\Support;

use App\Models\Order;

class ReceiptPayload
{
    public static function make(Order $o, $business): array
    {
        $o->loadMissing(['items', 'payments', 'outlet', 'cashier']);
        $custom = $business->canUse('custom_receipt');

        return [
            'business' => $business->name,
            'logo_url' => $custom && $business->receipt_logo_path ? '/storage/' . $business->receipt_logo_path : null,
            'cashier' => $o->cashier?->name,
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
                'date' => $o->created_at->timezone($business->timezone)->format('d/m/Y H:i'),
                'items' => $o->items->map(fn($i) => [
                    'name' => $i->name_snapshot,
                    'unit' => $i->unit_name,
                    'qty' => $i->qty,
                    'price' => $i->price_snapshot,
                    'subtotal' => $i->subtotal,
                ])->values(),
                'subtotal' => $o->subtotal,
                'discount' => $o->discount,
                'total' => $o->total,
                'paid' => $o->paid,
                'change' => $o->change_amount,
                'payments' => $o->payments->map(fn($p) => [
                    'method' => $p->method,
                    'amount' => $p->amount,
                    'ref' => $p->ref,
                ])->values(),
            ],
        ];
    }
}
