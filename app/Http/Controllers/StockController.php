<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $outlets = $business->outlets()->orderBy('created_at')->get(['id', 'name']);
        $outlet = $outlets->firstWhere('id', $request->outlet) ?? $outlets->first();

        $locked = ! $business->canUse('stock_management');

        $products = $locked || ! $outlet ? null : Product::query()
            ->select('products.*')
            ->addSelect(['stock_qty' => Stock::select('qty')
                ->whereColumn('product_id', 'products.id')
                ->where('outlet_id', $outlet->id)
                ->limit(1)])
            ->with('units:id,product_id,name,conversion')
            ->where('track_stock', true)
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q
                ->where('name', 'ilike', "%{$s}%")
                ->orWhere('sku', 'ilike', "%{$s}%")
                ->orWhere('barcode', 'ilike', "%{$s}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('stock/Index', [
            'locked' => $locked,
            'outlets' => $outlets,
            'outlet_id' => $outlet?->id,
            'products' => $products,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function adjust(Request $request, StockService $stock)
    {
        $business = $request->attributes->get('business');
        abort_unless($business->canUse('stock_management'), 403, 'Manajemen stok tersedia mulai paket Rintis.');

        $data = $request->validate([
            'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('business_id', $business->id)],
            'product_id' => ['required', 'string'],
            'mode' => ['required', 'in:add,subtract,set'],
            'qty' => ['required', 'integer', 'min:0'],
            'unit_name' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $base = $stock->toBase($product, $data['unit_name'] ?? null, (int) $data['qty']);

        match ($data['mode']) {
            'add' => $stock->move($data['outlet_id'], $product, 'purchase', $base, $data['note'] ?? 'Stok masuk'),
            'subtract' => $stock->move($data['outlet_id'], $product, 'adjustment', -$base, $data['note'] ?? 'Stok keluar'),
            'set' => $stock->setTo($data['outlet_id'], $product, $base, $data['note'] ?? 'Stok opname'),
        };

        return back();
    }
}
