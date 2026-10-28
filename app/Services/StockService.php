<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /** Ubah stok sebesar $delta (+ masuk, - keluar), dalam satuan dasar. */
    public function move(string $outletId, Product $product, string $type, int $delta, ?string $note = null, ?string $refType = null, ?string $refId = null, bool $allowNegative = true): StockMovement
    {
        return DB::transaction(function () use ($outletId, $product, $type, $delta, $note, $refType, $refId, $allowNegative) {
            $stock = $this->lock($outletId, $product);

            if (! $allowNegative && $stock->qty + $delta < 0) {
                throw ValidationException::withMessages([
                    'items' => "Stok {$product->name} tidak cukup (tersisa {$stock->qty} {$product->unit}).",
                ]);
            }

            return $this->apply($stock, $type, $delta, $note, $refType, $refId);
        });
    }

    /** Set stok ke angka tertentu (hasil hitung fisik). */
    public function setTo(string $outletId, Product $product, int $target, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($outletId, $product, $target, $note) {
            $stock = $this->lock($outletId, $product);

            return $this->apply($stock, 'adjustment', $target - $stock->qty, $note);
        });
    }

    /** Konversi jumlah satuan (mis. 2 dus) ke satuan dasar (48 pcs). */
    public function toBase(Product $product, ?string $unitName, int $qty): int
    {
        if (! $unitName) {
            return $qty;
        }

        $unit = $product->units()->where('name', $unitName)->first();
        abort_unless($unit, 422, 'Satuan tidak ditemukan.');

        return $qty * $unit->conversion;
    }

    private function lock(string $outletId, Product $product): Stock
    {
        $stock = Stock::firstOrCreate(['outlet_id' => $outletId, 'product_id' => $product->id], ['qty' => 0]);

        return Stock::whereKey($stock->id)->lockForUpdate()->first();
    }

    private function apply(Stock $stock, string $type, int $delta, ?string $note, ?string $refType = null, ?string $refId = null): StockMovement
    {
        $stock->qty += $delta;
        $stock->save();

        return StockMovement::create([
            'outlet_id' => $stock->outlet_id,
            'product_id' => $stock->product_id,
            'type' => $type,
            'qty' => $delta,
            'balance_after' => $stock->qty,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }
}
