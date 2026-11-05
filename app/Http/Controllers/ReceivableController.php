<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Shift;
use App\Services\ReceivableService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceivableController extends Controller
{
    public function store(Request $request, string $id, ReceivableService $service)
    {
        $business = $request->attributes->get('business');

        abort_unless($business->canUse('receivable'), 403, 'Fitur piutang tersedia mulai paket Sultan.');
        abort_unless(in_array($business->roleOf($request->user()), ['owner', 'admin', 'kasir'], true), 403, 'Kamu tidak punya akses ke menu ini.');

        $customer = Customer::findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'method' => ['required', 'in:cash,qris,debit,transfer'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        $shift = Shift::where('user_id', $request->user()->id)->where('status', 'open')->first();

        if ($data['method'] === 'cash' && ! $shift) {
            throw ValidationException::withMessages(['amount' => 'Buka kasir dulu untuk menerima pembayaran tunai.']);
        }

        $service->pay($customer, (int) $data['amount'], $data['method'], $shift, $data['note'] ?? null);

        return back();
    }
}
