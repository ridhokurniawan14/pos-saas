<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class PricingService
{
    /** @return array{price:int, conversion:int, cost:int} */
    public function resolve(Product $p, ?string $unitName, int $qty, bool $wholesale): array
    {
        $conversion = 1;
        $price = $p->sell_price;

        if ($unitName) {
            $unit = $wholesale ? $p->units->firstWhere('name', $unitName) : null;

            if (! $unit) {
                throw ValidationException::withMessages(['items' => "Satuan {$unitName} tidak tersedia untuk {$p->name}."]);
            }

            $conversion = $unit->conversion;
            $price = $unit->sell_price;
        }

        if ($wholesale) {
            $tier = $p->priceTiers
                ->filter(fn($t) => ($t->unit_name ?: null) === ($unitName ?: null) && $t->min_qty <= $qty)
                ->sortByDesc('min_qty')
                ->first();

            if ($tier) {
                $price = $tier->price;
            }
        }

        return ['price' => (int) $price, 'conversion' => $conversion, 'cost' => (int) $p->cost_price * $conversion];
    }
}
