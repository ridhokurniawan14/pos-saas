<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, SoftDeletes, BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];
    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function units()
    {
        return $this->hasMany(ProductUnit::class)->orderBy('conversion');
    }
    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_qty');
    }
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/' . $this->image_path : null;
    }
}
