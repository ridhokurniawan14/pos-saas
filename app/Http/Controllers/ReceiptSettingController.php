<?php

namespace App\Http\Controllers;

use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReceiptSettingController extends Controller
{
    public function index(Request $request)
    {
        $business = $this->manager($request);

        return Inertia::render('receipt-settings/Index', [
            'business' => [
                'name' => $business->name,
                'allow_negative_stock' => $business->allow_negative_stock,
                'logo_url' => $business->receipt_logo_path ? '/storage/' . $business->receipt_logo_path : null,
            ],
            'outlets' => $business->outlets()->orderBy('created_at')
                ->get(['id', 'name', 'address', 'phone', 'receipt_header', 'receipt_footer']),
            'can_custom' => $business->canUse('custom_receipt'),
        ]);
    }

    public function business(Request $request)
    {
        $business = $this->manager($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'allow_negative_stock' => ['required', 'boolean'],
        ]);

        $business->update($data);

        return back();
    }

    public function outlet(Request $request, string $id)
    {
        $business = $this->manager($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'receipt_header' => ['nullable', 'string', 'max:300'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $business->canUse('custom_receipt')) {
            unset($data['receipt_header'], $data['receipt_footer']);
        }

        $business->outlets()->findOrFail($id)->update($data);

        return back();
    }

    public function logo(Request $request)
    {
        $business = $this->manager($request);
        abort_unless($business->canUse('custom_receipt'), 403, 'Logo struk tersedia mulai paket Tumbuh.');

        $data = $request->validate(['logo_data' => ['required', 'string', 'max:2000000']]);
        $path = ImageStore::fromDataUrl($data['logo_data'], "logos/{$business->id}", 1024 * 1024);

        if ($business->receipt_logo_path) {
            Storage::disk('public')->delete($business->receipt_logo_path);
        }
        $business->update(['receipt_logo_path' => $path]);

        return back();
    }

    public function destroyLogo(Request $request)
    {
        $business = $this->manager($request);

        if ($business->receipt_logo_path) {
            Storage::disk('public')->delete($business->receipt_logo_path);
            $business->update(['receipt_logo_path' => null]);
        }

        return back();
    }

    private function manager(Request $request)
    {
        $business = $request->attributes->get('business');
        abort_unless($business->isManager($request->user()), 403, 'Hanya owner/admin yang boleh mengubah pengaturan toko.');

        return $business;
    }
}
