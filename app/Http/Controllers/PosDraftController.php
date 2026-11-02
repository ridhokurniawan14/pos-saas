<?php

namespace App\Http\Controllers;

use App\Models\PosDraft;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosDraftController extends Controller
{
    public function store(Request $request)
    {
        $business = $request->attributes->get('business');

        $shift = Shift::where('user_id', $request->user()->id)->where('status', 'open')->first();
        if (! $shift) {
            throw ValidationException::withMessages(['draft' => 'Kasir belum dibuka.']);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'items.*.unit_name' => ['nullable', 'string', 'max:30'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'discount_type' => ['nullable', 'in:rp,percent'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
        ]);

        if (PosDraft::count() >= 30) {
            throw ValidationException::withMessages(['draft' => 'Draft penuh (maksimal 30). Hapus draft lama dulu.']);
        }

        PosDraft::create([
            'outlet_id' => $shift->outlet_id,
            'user_id' => $request->user()->id,
            'label' => $data['label'] ?? null,
            'items' => $data['items'],
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => (int) ($data['discount_value'] ?? 0),
        ]);

        return back();
    }

    public function destroy(string $id)
    {
        PosDraft::findOrFail($id)->delete();

        return back();
    }
}
