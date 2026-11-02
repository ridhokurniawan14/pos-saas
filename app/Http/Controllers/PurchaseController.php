<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Locked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->attributes->get('business')->canUse('stock_management')) {
            return Locked::page('Pembelian Barang');
        }
        $this->access($request);

        $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $purchases = Purchase::with('supplier:id,name')
            ->withCount('items')
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q
                ->where('number', 'ilike', "%{$s}%")
                ->orWhere('note', 'ilike', "%{$s}%")
                ->orWhereHas('supplier', fn($q) => $q->where('name', 'ilike', "%{$s}%"))))
            ->when($request->from, fn($q, $d) => $q->whereDate('purchase_date', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('purchase_date', '<=', $d))
            ->latest('purchase_date')->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'date' => $p->purchase_date->format('d/m/Y'),
                'supplier' => $p->supplier?->name,
                'items_count' => $p->items_count,
                'total' => $p->total,
            ]);

        return Inertia::render('purchases/Index', [
            'purchases' => $purchases,
            'filters' => ['search' => $request->search, 'from' => $request->from, 'to' => $request->to],
        ]);
    }

    public function create(Request $request)
    {
        $business = $request->attributes->get('business');
        if (! $request->attributes->get('business')->canUse('stock_management')) {
            return Locked::page('Pembelian Barang');
        }
        $this->access($request);
        $wholesale = $business->canUse('wholesale');

        $products = Product::with('units:id,product_id,name,conversion')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'unit', 'cost_price'])
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'unit' => $p->unit,
                'cost_price' => $p->cost_price,
                'units' => $wholesale ? $p->units->map(fn($u) => ['name' => $u->name, 'conversion' => $u->conversion])->values() : [],
            ]);

        return Inertia::render('purchases/Create', [
            'outlets' => $business->outlets()->where('is_active', true)->orderBy('created_at')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products' => $products,
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $business = $request->attributes->get('business');
        $this->access($request);

        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'purchase_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:200'],
            'update_cost' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.unit_name' => ['nullable', 'string', 'max:30'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.cost' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ]);

        // Terkirim dua kali -> abaikan yang kedua
        if (Purchase::find($data['id'])) {
            return redirect('/purchases');
        }

        DB::transaction(function () use ($data, $business, $stock, $request) {
            $wholesale = $business->canUse('wholesale');
            $products = Product::with('units')
                ->whereIn('id', collect($data['items'])->pluck('product_id')->unique())
                ->get()->keyBy('id');

            $number = 'PO-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
            $lines = [];
            $total = 0;
            $stockIn = [];
            $newCost = [];

            foreach ($data['items'] as $it) {
                $p = $products[$it['product_id']] ?? null;
                if (! $p) {
                    throw ValidationException::withMessages(['items' => 'Ada produk yang tidak ditemukan.']);
                }

                $name = ($it['unit_name'] ?? null) ?: null;
                $conv = 1;
                if ($name) {
                    $unit = $wholesale ? $p->units->firstWhere('name', $name) : null;
                    if (! $unit) {
                        throw ValidationException::withMessages(['items' => "Satuan {$name} tidak tersedia untuk {$p->name}."]);
                    }
                    $conv = $unit->conversion;
                }

                $subtotal = $it['qty'] * $it['cost'];
                $total += $subtotal;
                $lines[] = [
                    'product_id' => $p->id,
                    'name_snapshot' => $p->name,
                    'unit_name' => $name,
                    'conversion' => $conv,
                    'qty' => $it['qty'],
                    'cost' => $it['cost'],
                    'subtotal' => $subtotal,
                ];

                if ($p->track_stock) {
                    $stockIn[$p->id] = ($stockIn[$p->id] ?? 0) + $it['qty'] * $conv;
                }
                $newCost[$p->id] = (int) round($it['cost'] / $conv); // modal per satuan dasar
            }

            $purchase = Purchase::create([
                'id' => $data['id'],
                'outlet_id' => $data['outlet_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'number' => $number,
                'purchase_date' => $data['purchase_date'],
                'total' => $total,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($lines as $l) {
                $purchase->items()->create($l);
            }

            ksort($stockIn);
            foreach ($stockIn as $productId => $baseQty) {
                $stock->move($data['outlet_id'], $products[$productId], 'purchase', $baseQty, 'Pembelian ' . $number, 'purchase', $purchase->id);
            }

            if ($data['update_cost'] ?? false) {
                foreach ($newCost as $productId => $cost) {
                    $products[$productId]->update(['cost_price' => $cost]);
                }
            }
        });

        return redirect('/purchases');
    }

    public function show(Request $request, string $id)
    {
        $business = $request->attributes->get('business');
        $this->access($request);

        $p = Purchase::with(['items', 'supplier', 'outlet:id,name', 'creator:id,name'])->findOrFail($id);

        return Inertia::render('purchases/Show', [
            'purchase' => [
                'number' => $p->number,
                'date' => $p->purchase_date->format('d/m/Y'),
                'supplier' => $p->supplier?->name,
                'outlet' => $p->outlet?->name,
                'creator' => $p->creator?->name,
                'note' => $p->note,
                'total' => $p->total,
                'items' => $p->items->map(fn($i) => [
                    'name' => $i->name_snapshot,
                    'unit' => $i->unit_name,
                    'conversion' => $i->conversion,
                    'qty' => $i->qty,
                    'cost' => $i->cost,
                    'subtotal' => $i->subtotal,
                ])->values(),
            ],
        ]);
    }

    private function access(Request $request): void
    {
        $b = $request->attributes->get('business');
        abort_unless($b->canUse('stock_management'), 403, 'Supplier dan pembelian tersedia mulai paket Rintis.');
        abort_unless(in_array($b->roleOf($request->user()), ['owner', 'admin', 'gudang'], true), 403, 'Kamu tidak punya akses ke menu ini.');
    }
}
