<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Shift;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    private const LIMIT_KEY = ['admin' => 'max_admins', 'kasir' => 'max_cashiers', 'gudang' => 'max_warehouse'];
    private const LABEL = ['admin' => 'admin', 'kasir' => 'kasir', 'gudang' => 'staf gudang'];

    public function index(Request $request)
    {
        $business = $this->owner($request);
        $tz = $business->timezone;

        $members = $business->users()->orderBy('business_user.created_at')->get()->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
            'joined' => $u->pivot->created_at?->timezone($tz)->format('d/m/Y'),
            'is_me' => $u->id === $request->user()->id,
        ])->values();

        $pending = Invitation::where('business_id', $business->id)
            ->whereNull('accepted_at')->where('expires_at', '>', now())
            ->latest()->get()
            ->map(fn($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'expires' => $i->expires_at->timezone($tz)->format('d/m/Y'),
            ])->values();

        $usage = [];
        foreach ($this->usage($business) as $role => $used) {
            $usage[$role] = ['used' => $used, 'limit' => $business->limit(self::LIMIT_KEY[$role])];
        }

        return Inertia::render('users/Index', [
            'members' => $members,
            'pending' => $pending,
            'usage' => $usage,
            'matrix' => Access::ROLES,
            'plan' => $business->subscription?->plan?->name,
        ]);
    }

    public function invite(Request $request)
    {
        $business = $this->owner($request);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
            'role' => ['required', Rule::in(array_keys(self::LIMIT_KEY))],
        ]);
        $email = strtolower(trim($data['email']));

        $isMember = DB::table('business_user')
            ->join('users', 'users.id', '=', 'business_user.user_id')
            ->where('business_user.business_id', $business->id)
            ->whereRaw('lower(users.email) = ?', [$email])
            ->exists();

        if ($isMember) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah menjadi anggota toko.']);
        }

        $hasPending = Invitation::pendingEmail($email)->where('business_id', $business->id)->exists();
        if ($hasPending) {
            throw ValidationException::withMessages(['email' => 'Undangan untuk email ini masih menunggu. Batalkan dulu kalau ingin mengubah peran.']);
        }

        $this->assertRoom($business, $data['role']);

        Invitation::create([
            'business_id' => $business->id,
            'email' => $email,
            'role' => $data['role'],
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        return back();
    }

    public function cancelInvite(Request $request, string $id)
    {
        $business = $this->owner($request);

        Invitation::where('business_id', $business->id)->whereNull('accepted_at')->whereKey($id)->firstOrFail()->delete();

        return back();
    }

    public function updateRole(Request $request, string $userId)
    {
        $business = $this->owner($request);

        $data = $request->validate(['role' => ['required', Rule::in(array_keys(self::LIMIT_KEY))]]);
        $row = $this->member($business, $userId);

        if ($row->role === 'owner' || (string) $userId === (string) $request->user()->id) {
            throw ValidationException::withMessages(['role' => 'Peran pemilik atau peran sendiri tidak bisa diubah.']);
        }

        if ($row->role !== $data['role']) {
            $this->assertRoom($business, $data['role']);

            DB::table('business_user')
                ->where('business_id', $business->id)->where('user_id', $userId)
                ->update(['role' => $data['role'], 'updated_at' => now()]);
        }

        return back();
    }

    public function remove(Request $request, string $userId)
    {
        $business = $this->owner($request);
        $row = $this->member($business, $userId);

        if ($row->role === 'owner' || (string) $userId === (string) $request->user()->id) {
            throw ValidationException::withMessages(['member' => 'Pemilik atau akun sendiri tidak bisa dihapus.']);
        }

        if (Shift::where('user_id', $userId)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['member' => 'Anggota ini masih punya shift terbuka. Minta dia menutup kasir dulu.']);
        }

        DB::transaction(function () use ($business, $userId) {
            DB::table('business_user')->where('business_id', $business->id)->where('user_id', $userId)->delete();
            DB::table('outlet_user')->where('user_id', $userId)
                ->whereIn('outlet_id', $business->outlets()->withTrashed()->pluck('id'))->delete();
        });

        return back();
    }

    private function usage($business): array
    {
        $members = DB::table('business_user')
            ->where('business_id', $business->id)
            ->whereIn('role', array_keys(self::LIMIT_KEY))
            ->selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role');

        $pending = Invitation::where('business_id', $business->id)
            ->whereNull('accepted_at')->where('expires_at', '>', now())
            ->selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role');

        $out = [];
        foreach (array_keys(self::LIMIT_KEY) as $role) {
            $out[$role] = (int) ($members[$role] ?? 0) + (int) ($pending[$role] ?? 0);
        }

        return $out;
    }

    private function assertRoom($business, string $role): void
    {
        $limit = $business->limit(self::LIMIT_KEY[$role]);

        if ($limit === -1) {
            return;
        }

        if ($this->usage($business)[$role] >= $limit) {
            $plan = $business->subscription?->plan?->name;
            $msg = $limit === 0
                ? "Paket {$plan} belum menyediakan peran " . self::LABEL[$role] . '. Upgrade paket untuk menambahkannya.'
                : "Batas {$limit} " . self::LABEL[$role] . " untuk paket {$plan} sudah tercapai. Upgrade paket untuk menambah.";

            throw ValidationException::withMessages(['role' => $msg]);
        }
    }

    private function member($business, string $userId): object
    {
        $row = DB::table('business_user')->where('business_id', $business->id)->where('user_id', $userId)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function owner(Request $request)
    {
        $business = $request->attributes->get('business');
        abort_unless($business->roleOf($request->user()) === 'owner', 403, 'Hanya pemilik toko yang boleh mengelola pengguna.');

        return $business;
    }
}
