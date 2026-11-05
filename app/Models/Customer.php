<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasUuids, SoftDeletes, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function receivablePayments()
    {
        return $this->hasMany(ReceivablePayment::class);
    }
    public function pointMovements()
    {
        return $this->hasMany(PointMovement::class);
    }
}
