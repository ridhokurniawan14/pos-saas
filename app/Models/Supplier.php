<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasUuids, SoftDeletes, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
