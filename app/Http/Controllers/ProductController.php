<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Support\ImageStore;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');

        $products = Product::with('category:id,name')
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                    ->orWhere('sku', 'ilike', "%{$s}%")
                    ->orWhere('barcode', 'ilike', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => ['search' => $request->search],
            'usage' => ['count' => Product::count(), 'limit' => $business->limit('max_products')],
        ]);
    }

    public function create()
    {
        return Inertia::render('products/Form', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'product' => null,
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->attributes->get('business');
        $limit = $business->limit('max_products');

        if ($limit !== -1 && Product::count() >= $limit) {
            return back()->withErrors([
                'limit' => "Batas {$limit} produk untuk paket {$business->subscription->plan->name} sudah tercapai. Upgrade paket untuk menambah produk.",
            ]);
        }

        DB::transaction(function () use ($request, $business) {
            $data = $this->handleImage($request, $this->validated($request, $business->id), $business->id);
            $product = Product::create($data);
            $this->syncWholesale($request, $business, $product);
        });

        return redirect('/products');
    }

    public function edit(Request $request, string $id)
    {
        return Inertia::render('products/Form', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'product' => Product::with(['units', 'priceTiers'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $business = $request->attributes->get('business');
        $product = Product::findOrFail($id);

        DB::transaction(function () use ($request, $business, $product) {
            $data = $this->handleImage($request, $this->validated($request, $business->id, $product->id), $business->id, $product);
            $product->update($data);
            $this->syncWholesale($request, $business, $product);
        });

        return redirect('/products');
    }

    public function destroy(string $id)
    {
        Product::findOrFail($id)->delete();

        return back();
    }

    private function validated(Request $request, string $businessId, ?string $ignoreId = null): array
    {
        $unique = fn(string $col) => Rule::unique('products', $col)
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->ignore($ignoreId);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'sku' => ['nullable', 'string', 'max:50', $unique('sku')],
            'barcode' => ['nullable', 'string', 'max:50', $unique('barcode')],
            'unit' => ['required', 'string', 'max:20'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'track_stock' => ['boolean'],
            'is_active' => ['boolean'],
            'image_data' => ['nullable', 'string', 'max:4000000'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    private function handleImage(Request $request, array $data, string $businessId, ?Product $product = null): array
    {
        $dataUrl = $data['image_data'] ?? null;
        unset($data['image_data'], $data['remove_image'], $data['image']);

        if ($dataUrl) {
            $data['image_path'] = ImageStore::fromDataUrl($dataUrl, "products/{$businessId}");
            if ($product?->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
        } elseif ($request->boolean('remove_image') && $product?->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        return $data;
    }

    private function syncWholesale(Request $request, $business, Product $product): void
    {
        if (! $business->canUse('wholesale')) {
            return;
        }

        $data = $request->validate([
            'units' => ['array', 'max:5'],
            'units.*.name' => ['required', 'string', 'max:30'],
            'units.*.conversion' => ['required', 'integer', 'min:2'],
            'units.*.sell_price' => ['required', 'integer', 'min:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:50'],
            'tiers' => ['array', 'max:10'],
            'tiers.*.unit_name' => ['nullable', 'string', 'max:30'],
            'tiers.*.min_qty' => ['required', 'integer', 'min:2'],
            'tiers.*.price' => ['required', 'integer', 'min:0'],
        ]);

        $unitNames = collect($data['units'] ?? [])->pluck('name');
        $seen = [];
        foreach ($data['tiers'] ?? [] as $i => $t) {
            $name = $t['unit_name'] ?? null;

            if ($name && ! $unitNames->contains($name)) {
                throw ValidationException::withMessages(["tiers.$i.unit_name" => 'Satuan tidak ditemukan.']);
            }

            $key = ($name ?? '') . '|' . $t['min_qty'];
            if (in_array($key, $seen)) {
                throw ValidationException::withMessages(["tiers.$i.min_qty" => 'Min qty ganda untuk satuan yang sama.']);
            }
            $seen[] = $key;
        }

        $product->units()->delete();
        foreach ($data['units'] ?? [] as $u) {
            $product->units()->create($u);
        }

        $product->priceTiers()->delete();
        foreach ($data['tiers'] ?? [] as $t) {
            $product->priceTiers()->create($t);
        }
    }
}
