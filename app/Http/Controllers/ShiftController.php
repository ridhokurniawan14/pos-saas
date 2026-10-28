<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Shift;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $manager = $business->isManager($request->user());
        $tz = $business->timezone;

        $shifts = Shift::with(['user:id,name', 'outlet:id,name'])
            ->when(! $manager, fn($q) => $q->where('user_id', $request->user()->id))
            ->latest('opened_at')
            ->paginate(20)
            ->through(fn($s) => [
                'id' => $s->id,
                'cashier' => $s->user?->name,
                'outlet' => $s->outlet?->name,
                'opened_at' => $s->opened_at->timezone($tz)->format('d/m/Y H:i'),
                'closed_at' => $s->closed_at?->timezone($tz)->format('d/m/Y H:i'),
                'opening_cash' => $s->opening_cash,
                'difference' => $s->difference,
                'status' => $s->status,
            ]);

        return Inertia::render('shifts/Index', ['shifts' => $shifts]);
    }

    public function show(Request $request, string $id)
    {
        $business = $request->attributes->get('business');
        $shift = Shift::with(['user:id,name', 'outlet:id,name'])->findOrFail($id);

        abort_unless(
            $business->isManager($request->user()) || $shift->user_id === $request->user()->id,
            403,
            'Kamu tidak punya akses ke shift ini.',
        );

        $data = $shift->payload($business->timezone);

        return Inertia::render('shifts/Show', [
            'shift' => [
                ...$data,
                'status' => $shift->status,
                'cashier' => $shift->user?->name,
                'closed_at' => $shift->closed_at?->timezone($business->timezone)->format('d/m/Y H:i'),
                'closing_cash' => $shift->closing_cash,
                'difference' => $shift->difference,
                'note' => $shift->note,
                'expected_cash' => $shift->status === 'closed' ? $shift->expected_cash : $data['expected_cash'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->attributes->get('business');

        $data = $request->validate([
            'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'opening_cash' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ]);

        if (Shift::where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Kamu masih punya shift yang terbuka.']);
        }

        try {
            Shift::create([
                'outlet_id' => $data['outlet_id'],
                'user_id' => $request->user()->id,
                'opened_at' => now(),
                'opening_cash' => $data['opening_cash'],
                'status' => 'open',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['shift' => 'Kamu masih punya shift yang terbuka.']);
        }

        return back();
    }

    public function cash(Request $request, string $id)
    {
        $shift = $this->ownOpenShift($request, $id);

        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'reason' => ['required', 'string', 'max:100'],
        ]);

        if ($data['type'] === 'out' && $data['amount'] > $shift->summary()['expected_cash']) {
            throw ValidationException::withMessages(['amount' => 'Kas keluar melebihi uang yang ada di laci.']);
        }

        CashMovement::create([
            'shift_id' => $shift->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'created_by' => $request->user()->id,
        ]);

        return back();
    }

    public function close(Request $request, string $id)
    {
        $data = $request->validate([
            'closing_cash' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            $shift = Shift::whereKey($id)->lockForUpdate()->firstOrFail();

            abort_unless($shift->user_id === $request->user()->id, 403, 'Hanya pemilik shift yang boleh menutup.');

            if ($shift->status !== 'open') {
                throw ValidationException::withMessages(['shift' => 'Shift ini sudah ditutup.']);
            }

            $expected = $shift->summary()['expected_cash'];

            $shift->update([
                'closed_at' => now(),
                'closing_cash' => $data['closing_cash'],
                'expected_cash' => $expected,
                'difference' => $data['closing_cash'] - $expected,
                'note' => $data['note'] ?? null,
                'status' => 'closed',
            ]);
        });

        return redirect("/shifts/{$id}");
    }

    private function ownOpenShift(Request $request, string $id): Shift
    {
        $shift = Shift::findOrFail($id);

        abort_unless($shift->user_id === $request->user()->id, 403, 'Bukan shift kamu.');

        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => 'Shift ini sudah ditutup.']);
        }

        return $shift;
    }
}
