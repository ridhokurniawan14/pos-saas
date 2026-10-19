<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $id = static::currentBusinessId();

            if ($id) {
                $query->where($query->getModel()->getTable() . '.business_id', $id);
            } elseif (! app()->runningInConsole()) {
                $query->whereRaw('1 = 0'); // tanpa bisnis aktif, jangan kembalikan data apa pun
            }
        });

        static::creating(function ($model) {
            if (! $model->business_id) {
                $model->business_id = static::currentBusinessId();
            }
        });
    }

    protected static function currentBusinessId(): ?string
    {
        if (app()->bound('current_business_id')) {
            return app('current_business_id');
        }

        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $id = $user->businesses()->value('businesses.id');

        if ($id) {
            app()->instance('current_business_id', $id);
        }

        return $id;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
