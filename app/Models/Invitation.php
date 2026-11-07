<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Invitation extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public static function pendingEmail(string $email): Builder
    {
        return static::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereRaw('lower(email) = ?', [strtolower($email)]);
    }

    /** Terima semua undangan aktif untuk email pengguna ini. Aman dipanggil berulang. */
    public static function acceptFor(User $user): int
    {
        $accepted = 0;

        foreach (static::pendingEmail($user->email)->get() as $invite) {
            DB::transaction(function () use ($invite, $user, &$accepted) {
                $inv = static::whereKey($invite->id)->lockForUpdate()->first();

                if (! $inv || $inv->accepted_at) {
                    return;
                }

                DB::table('business_user')->updateOrInsert(
                    ['business_id' => $inv->business_id, 'user_id' => $user->id],
                    ['role' => $inv->role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
                );

                $inv->update(['accepted_at' => now()]);
                session(['business_id' => $inv->business_id]);
                $accepted++;
            });
        }

        return $accepted;
    }
}
