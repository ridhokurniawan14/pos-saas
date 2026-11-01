<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $this->access($request);

        return Inertia::render('suppliers/Index', [
            'suppliers' => Supplier::withCount('purchases')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->access($request);
        Supplier::create($this->rules($request));

        return back();
    }

    public function update(Request $request, string $id)
    {
        $this->access($request);
        Supplier::findOrFail($id)->update($this->rules($request));

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        $this->access($request);
        Supplier::findOrFail($id)->delete();

        return back();
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function access(Request $request): void
    {
        $b = $request->attributes->get('business');
        abort_unless($b->canUse('stock_management'), 403, 'Supplier dan pembelian tersedia mulai paket Rintis.');
        abort_unless(in_array($b->roleOf($request->user()), ['owner', 'admin', 'gudang'], true), 403, 'Kamu tidak punya akses ke menu ini.');
    }
}
