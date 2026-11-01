<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasUuids, BelongsToBusiness;

    protected $guarded = ['business_id']; // id boleh diisi (UUID dari browser)

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'total' => 'integer'];
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
