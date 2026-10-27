<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuids, BelongsToBusiness;

    protected $guarded = ['business_id']; // id boleh diisi (UUID dari client)

    protected function casts(): array
    {
        return [
            'created_at_client' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
