<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Business extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'allow_negative_stock' => 'boolean',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'status')->withTimestamps();
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest('created_at');
    }

    public function canUse(string $feature): bool
    {
        return (bool) $this->subscription?->plan?->features()
            ->where('feature_key', $feature)->where('enabled', true)->exists();
    }

    public function limit(string $key): int
    {
        return (int) ($this->subscription?->plan?->limits()
            ->where('key', $key)->value('value') ?? 0);
    }
    public function isReadOnly(): bool
    {
        $ends = $this->subscription?->ends_at;

        return in_array($this->status, ['expired', 'suspended'])
            || ($ends && $ends->isPast());
    }

    public function daysLeft(): int
    {
        $ends = $this->subscription?->ends_at;

        return $ends ? max(0, (int) ceil(now()->floatDiffInDays($ends, false))) : 0;
    }

    public function roleOf(User $user): ?string
    {
        return DB::table('business_user')
            ->where('business_id', $this->id)
            ->where('user_id', $user->id)
            ->value('role');
    }

    public function isManager(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }
}
