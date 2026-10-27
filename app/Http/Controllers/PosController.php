<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentSetting;
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
            'payment_methods' => PaymentSetting::current()->available(),
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
            'discount_type' => ['nullable', 'in:rp,percent'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1', 'max:3'],
            'payments.*.method' => ['required', 'string'],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'payments.*.bank_account_id' => ['nullable', 'uuid'],
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

        // Validasi metode pembayaran terhadap pengaturan bisnis
        $available = collect(PaymentSetting::current()->available())->keyBy('key');
        $payments = [];
        foreach ($data['payments'] as $pay) {
            if (! $available->has($pay['method'])) {
                throw ValidationException::withMessages(['payments' => 'Metode pembayaran tidak aktif.']);
            }

            $ref = null;
            if ($pay['method'] === 'transfer') {
                $bank = collect($available['transfer']['banks'])->firstWhere('id', $pay['bank_account_id'] ?? null);
                if (! $bank) {
                    throw ValidationException::withMessages(['payments' => 'Pilih rekening tujuan transfer.']);
                }
                $ref = $bank['bank_name'] . ' ' . $bank['account_number'];
            }

            $payments[] = ['method' => $pay['method'], 'amount' => (int) $pay['amount'], 'ref' => $ref];
        }

        try {
            $order = DB::transaction(function () use ($data, $payments, $business, $stock, $pricing) {
                $wholesale = $business->canUse('wholesale');
                $trackStock = $business->canUse('stock_management');

                $products = Product::with(['units', 'priceTiers'])
                    ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
                    ->get()->keyBy('id');

                $lines = [];
                $subtotal = 0;
                $stockOut = [];

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
                        'unit_name' => ($it['unit_name'] ?? null) ?: null,
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

                $dv = (int) ($data['discount_value'] ?? 0);
                $discount = ($data['discount_type'] ?? 'rp') === 'percent'
                    ? intdiv($subtotal * min($dv, 100) + 50, 100)
                    : $dv;
                $discount = min($discount, $subtotal);
                $total = $subtotal - $discount;
                $paid = (int) collect($payments)->sum('amount');
                $cash = (int) collect($payments)->where('method', 'cash')->sum('amount');
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
                foreach ($payments as $pay) {
                    $order->payments()->create($pay);
                }

                ksort($stockOut);
                foreach ($stockOut as $productId => $baseQty) {
                    $stock->move($data['outlet_id'], $products[$productId], 'sale', -$baseQty, 'Penjualan ' . $order->order_number, 'order', $order->id);
                }

                return $order->load(['items', 'payments', 'outlet', 'cashier']);
            });
        } catch (UniqueConstraintViolationException) {
            $order = Order::with(['items', 'payments', 'outlet', 'cashier'])->findOrFail($data['id']);
        }

        return response()->json($this->payload($order, $business));
    }

    private function payload(Order $o, $business): array
    {
        return \App\Support\ReceiptPayload::make($o, $business);
    }
}
