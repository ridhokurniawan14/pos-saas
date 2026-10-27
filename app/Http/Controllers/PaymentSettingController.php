<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Support\ImageStore;

class PaymentSettingController extends Controller
{
    public function index()
    {
        $s = PaymentSetting::current();

        return Inertia::render('payments/Index', [
            'settings' => [
                'cash_enabled' => $s->cash_enabled,
                'qris_enabled' => $s->qris_enabled,
                'debit_enabled' => $s->debit_enabled,
                'transfer_enabled' => $s->transfer_enabled,
                'qris_image_url' => $s->qris_image_path ? '/storage/' . $s->qris_image_path : null,
            ],
            'banks' => BankAccount::orderBy('bank_name')->get(['id', 'bank_name', 'account_number', 'account_name']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cash_enabled' => ['sometimes', 'boolean'],
            'qris_enabled' => ['sometimes', 'boolean'],
            'debit_enabled' => ['sometimes', 'boolean'],
            'transfer_enabled' => ['sometimes', 'boolean'],
        ]);

        PaymentSetting::current()->update($data);

        return back();
    }

    public function qris(Request $request)
    {
        $business = $request->attributes->get('business');
        $data = $request->validate(['qris_image_data' => ['required', 'string', 'max:5000000']]);

        $path = ImageStore::fromDataUrl($data['qris_image_data'], "payments/{$business->id}", 3 * 1024 * 1024);

        $s = PaymentSetting::current();
        if ($s->qris_image_path) {
            Storage::disk('public')->delete($s->qris_image_path);
        }
        $s->update(['qris_image_path' => $path]);

        return back();
    }

    public function destroyQris()
    {
        $s = PaymentSetting::current();
        if ($s->qris_image_path) {
            Storage::disk('public')->delete($s->qris_image_path);
        }
        $s->update(['qris_image_path' => null]);

        return back();
    }

    public function storeBank(Request $request)
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:50'],
            'account_number' => ['required', 'string', 'max:30', 'regex:/^[0-9\- ]+$/'],
            'account_name' => ['required', 'string', 'max:100'],
        ]);

        if (BankAccount::count() >= 10) {
            throw ValidationException::withMessages(['bank_name' => 'Maksimal 10 rekening.']);
        }

        BankAccount::create($data);

        return back();
    }

    public function destroyBank(string $id)
    {
        BankAccount::findOrFail($id)->delete();

        return back();
    }
}
