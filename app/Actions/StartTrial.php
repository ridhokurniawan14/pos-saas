<?php

namespace App\Actions;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartTrial
{
    public function handle(User $user): Business
    {
        return DB::transaction(function () use ($user) {
            $trialEnds = now()->addDays(14);

            $business = Business::create([
                'name' => 'Bisnis ' . $user->name,
                'slug' => Str::slug($user->name) . '-' . Str::lower(Str::random(5)),
                'owner_id' => $user->id,
                'trial_ends_at' => $trialEnds,
                'status' => 'trial',
            ]);

            $business->users()->attach($user->id, ['role' => 'owner']);

            $outlet = $business->outlets()->create(['name' => 'Cabang Utama']);
            $outlet->users()->attach($user->id);

            $business->subscription()->create([
                'plan_id' => Plan::where('code', 'trial')->value('id'),
                'status' => 'trialing',
                'starts_at' => now(),
                'ends_at' => $trialEnds,
            ]);

            return $business;
        });
    }
}
