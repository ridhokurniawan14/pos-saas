<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $wholesale = $business->canUse('wholesale');

        $products = Product::with(['units', 'priceTiers'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'unit' => $p->unit,
                'sell_price' => $p->sell_price,
                'category_id' => $p->category_id,
                'image_url' => $p->image_url,
                'units' => $wholesale ? $p->units->map(fn($u) => [
                    'name' => $u->name,
                    'conversion' => $u->conversion,
                    'sell_price' => $u->sell_price,
                ])->values() : [],
                'tiers' => $wholesale ? $p->priceTiers->map(fn($t) => [
                    'unit_name' => $t->unit_name,
                    'min_qty' => $t->min_qty,
                    'price' => $t->price,
                ])->values() : [],
            ]);

        return Inertia::render('pos/Index', [
            'business_name' => $business->name,
            'outlets' => $business->outlets()->where('is_active', true)->orderBy('created_at')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'products' => $products,
        ]);
    }

    public function store(Request $request, StockService $stock, PricingService $pricing)
    {
        $business = $request->attributes->get('business');

        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('business_id', $business->id)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.unit_name' => ['nullable', 'string', 'max:30'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1', 'max:3'],
            'payments.*.method' => ['required', 'in:cash,qris,debit,transfer'],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
            'created_at_client' => ['nullable', 'date'],
        ]);

        // Idempotensi: order yang sama dikirim ulang -> kembalikan yang sudah ada
        if ($existing = Order::with(['items', 'payments', 'outlet', 'cashier'])->find($data['id'])) {
            return response()->json($this->payload($existing, $business));
        }

        $limit = $business->limit('max_orders_per_month');
        if ($limit !== -1 && Order::where('created_at', '>=', now()->startOfMonth())->count() >= $limit) {
            throw ValidationException::withMessages([
                'limit' => "Batas {$limit} transaksi/bulan untuk paket {$business->subscription->plan->name} sudah tercapai. Upgrade paket.",
            ]);
        }

        try {
            $order = DB::transaction(function () use ($data, $business, $stock, $pricing) {
                $wholesale = $business->canUse('wholesale');
                $trackStock = $business->canUse('stock_management');

                $products = Product::with(['units', 'priceTiers'])
                    ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
                    ->get()->keyBy('id');

                $lines = [];
                $subtotal = 0;
                $stockOut = []; // product_id => qty satuan dasar

                foreach ($data['items'] as $it) {
                    $p = $products[$it['product_id']] ?? null;

                    if (! $p || ! $p->is_active) {
                        throw ValidationException::withMessages(['items' => 'Ada produk yang tidak ditemukan atau nonaktif.']);
                    }

                    $r = $pricing->resolve($p, $it['unit_name'] ?? null, $it['qty'], $wholesale);
                    $lineTotal = $r['price'] * $it['qty'];
                    $subtotal += $lineTotal;

                    $lines[] = [
                        'product_id' => $p->id,
                        'name_snapshot' => $p->name,
                        'unit_name' => $it['unit_name'] ?: null,
                        'conversion' => $r['conversion'],
                        'qty' => $it['qty'],
                        'price_snapshot' => $r['price'],
                        'cost_snapshot' => $r['cost'],
                        'subtotal' => $lineTotal,
                    ];

                    if ($trackStock && $p->track_stock) {
                        $stockOut[$p->id] = ($stockOut[$p->id] ?? 0) + $it['qty'] * $r['conversion'];
                    }
                }

                $discount = min((int) ($data['discount'] ?? 0), $subtotal);
                $total = $subtotal - $discount;
                $paid = (int) collect($data['payments'])->sum('amount');
                $cash = (int) collect($data['payments'])->where('method', 'cash')->sum('amount');
                $change = $paid - $total;

                if ($paid < $total) {
                    throw ValidationException::withMessages(['payments' => 'Pembayaran kurang dari total.']);
                }
                if ($change > $cash) {
                    throw ValidationException::withMessages(['payments' => 'Kembalian hanya boleh dari pembayaran tunai.']);
                }

                $order = Order::create([
                    'id' => $data['id'],
                    'outlet_id' => $data['outlet_id'],
                    'cashier_id' => auth()->id(),
                    'order_number' => 'INV-' . now()->format('ymd') . '-' . Str::upper(Str::random(5)),
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'paid' => $paid,
                    'change_amount' => $change,
                    'note' => $data['note'] ?? null,
                    'created_at_client' => $data['created_at_client'] ?? null,
                ]);

                foreach ($lines as $l) {
                    $order->items()->create($l);
                }
                foreach ($data['payments'] as $pay) {
                    $order->payments()->create(['method' => $pay['method'], 'amount' => $pay['amount']]);
                }

                ksort($stockOut); // urutan konsisten, cegah deadlock
                foreach ($stockOut as $productId => $baseQty) {
                    $stock->move($data['outlet_id'], $products[$productId], 'sale', -$baseQty, 'Penjualan ' . $order->order_number, 'order', $order->id);
                }

                return $order->load(['items', 'payments', 'outlet', 'cashier']);
            });
        } catch (UniqueConstraintViolationException) {
            // dua request bersamaan dengan UUID sama
            $order = Order::with(['items', 'payments', 'outlet', 'cashier'])->findOrFail($data['id']);
        }

        return response()->json($this->payload($order, $business));
    }

    private function payload(Order $o, $business): array
    {
        return [
            'business' => $business->name,
            'cashier' => $o->cashier?->name,
            'outlet' => [
                'name' => $o->outlet->name,
                'address' => $o->outlet->address,
                'phone' => $o->outlet->phone,
                'footer' => $o->outlet->receipt_footer,
            ],
            'order' => [
                'number' => $o->order_number,
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
                'payments' => $o->payments->map(fn($p) => ['method' => $p->method, 'amount' => $p->amount])->values(),
            ],
        ];
    }
}
