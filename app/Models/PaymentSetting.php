<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasUuids, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'cash_enabled' => 'boolean',
            'qris_enabled' => 'boolean',
            'debit_enabled' => 'boolean',
            'transfer_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], ['cash_enabled' => true]);
    }

    /** Hanya metode yang aktif DAN lengkap datanya yang tampil di kasir. */
    public function available(): array
    {
        $list = [];

        if ($this->cash_enabled) {
            $list[] = ['key' => 'cash', 'label' => 'Tunai'];
        }

        if ($this->qris_enabled && $this->qris_image_path) {
            $list[] = ['key' => 'qris', 'label' => 'QRIS', 'image_url' => '/storage/' . $this->qris_image_path];
        }

        if ($this->debit_enabled) {
            $list[] = ['key' => 'debit', 'label' => 'Debit'];
        }

        if ($this->transfer_enabled) {
            $banks = BankAccount::orderBy('bank_name')->get(['id', 'bank_name', 'account_number', 'account_name']);
            if ($banks->isNotEmpty()) {
                $list[] = ['key' => 'transfer', 'label' => 'Transfer', 'banks' => $banks->toArray()];
            }
        }

        return $list;
    }
}
