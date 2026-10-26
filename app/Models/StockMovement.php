<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasUuids, BelongsToBusiness;

    const UPDATED_AT = null;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'balance_after' => 'integer'];
    }
}
