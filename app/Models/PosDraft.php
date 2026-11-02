<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PosDraft extends Model
{
    use HasUuids, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'discount_value' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
