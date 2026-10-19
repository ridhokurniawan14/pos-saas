<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
