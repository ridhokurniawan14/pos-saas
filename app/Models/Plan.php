<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];

    public function limits()
    {
        return $this->hasMany(PlanLimit::class);
    }
    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }
}
