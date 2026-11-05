<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Locked;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\PointMovement;
use App\Models\ReceivablePayment;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->attributes->get('business')->canUse('customers')) {
            return Locked::page('Pelanggan', 'Tumbuh');
        }
        $this->guard($request);

        $request->validate(['search' => ['nullable', 'string', 'max:50']]);
        $business = $request->attributes->get('business');
        $done = fn($q) => $q->where('status', 'completed');

        $customers = Customer::query()
            ->withCount(['orders as orders_count' => $done])
            ->withSum(['orders as total_spent' => $done], 'total')
            ->withSum(['orders as due_total' => $done], 'due_amount')
            ->withSum('receivablePayments as repaid', 'amount')
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q
                ->where('name', 'ilike', "%{$s}%")
                ->orWhere('phone', 'ilike', "%{$s}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'address' => $c->address,
                'orders_count' => (int) $c->orders_count,
                'total_spent' => (int) $c->total_spent,
                'debt' => max(0, (int) $c->due_total - (int) $c->repaid),
                'points' => (int) $c->points,
            ]);

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $request->search],
            'manager' => $business->isManager($request->user()),
            'can' => [
                'receivable' => $business->canUse('receivable'),
                'loyalty' => $business->canUse('loyalty') && $business->loyalty_enabled,
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $business = $request->attributes->get('business');
        if (! $business->canUse('customers')) {
            return Locked::page('Pelanggan', 'Tumbuh');
        }
        $this->guard($request);

        $tz = $business->timezone;
        $canReceivable = $business->canUse('receivable');
        $canLoyalty = $business->canUse('loyalty') && $business->loyalty_enabled;

        $c = Customer::findOrFail($id);
        $done = Order::where('customer_id', $c->id)->where('status', 'completed');

        $orders = Order::where('customer_id', $c->id)->latest()->limit(30)
            ->get(['id', 'order_number', 'total', 'due_amount', 'status', 'created_at']);

        $unpaid = collect();
        $payments = collect();
        if ($canReceivable) {
            $repaid = ReceivablePayment::where('customer_id', $c->id)
                ->selectRaw('order_id, SUM(amount) as s')->groupBy('order_id')->pluck('s', 'order_id');

            $unpaid = Order::where('customer_id', $c->id)->where('status', 'completed')->where('due_amount', '>', 0)
                ->orderBy('created_at')->get(['id', 'order_number', 'total', 'due_amount', 'created_at'])
                ->map(fn($o) => [
                    'id' => $o->id,
                    'number' => $o->order_number,
                    'date' => $o->created_at->timezone($tz)->format('d/m/Y'),
                    'total' => $o->total,
                    'remaining' => max(0, $o->due_amount - (int) ($repaid[$o->id] ?? 0)),
                ])->filter(fn($o) => $o['remaining'] > 0)->values();

            $payments = ReceivablePayment::where('customer_id', $c->id)
                ->selectRaw('batch_id, MAX(method) as method, SUM(amount) as total, MAX(created_at) as at, MAX(note) as note')
                ->groupBy('batch_id')->orderByDesc('at')->limit(20)->get()
                ->map(fn($p) => [
                    'batch_id' => $p->batch_id,
                    'method' => $p->method,
                    'total' => (int) $p->total,
                    'at' => \Illuminate\Support\Carbon::parse($p->at)->timezone($tz)->format('d/m/Y H:i'),
                    'note' => $p->note,
                ])->values();
        }

        $pointMoves = $canLoyalty
            ? PointMovement::where('customer_id', $c->id)->latest('created_at')->limit(20)
            ->get(['type', 'points', 'balance_after', 'note', 'created_at'])
            ->map(fn($m) => [
                'type' => $m->type,
                'points' => $m->points,
                'balance_after' => $m->balance_after,
                'note' => $m->note,
                'at' => $m->created_at->timezone($tz)->format('d/m/Y H:i'),
            ])->values()
            : collect();

        return Inertia::render('customers/Show', [
            'customer' => $c->only('id', 'name', 'phone', 'address'),
            'can' => ['receivable' => $canReceivable, 'loyalty' => $canLoyalty],
            'stats' => [
                'count' => (clone $done)->count(),
                'total' => (int) (clone $done)->sum('total'),
                'last' => (clone $done)->latest()->first()?->created_at?->timezone($tz)->format('d/m/Y'),
                'debt' => (int) $unpaid->sum('remaining'),
                'points' => (int) $c->points,
            ],
            'unpaid' => $unpaid,
            'payments' => $payments,
            'point_moves' => $pointMoves,
            'orders' => $orders->map(fn($o) => [
                'id' => $o->id,
                'number' => $o->order_number,
                'date' => $o->created_at->timezone($tz)->format('d/m/Y H:i'),
                'total' => $o->total,
                'due' => $o->due_amount,
                'status' => $o->status,
            ])->values(),
        ]);
    }

    /** Pencarian untuk kasir (JSON). */
    public function search(Request $request)
    {
        $this->guard($request);
        $s = trim((string) $request->query('search', ''));

        $rows = Customer::query()
            ->when($s, fn($q) => $q->where(fn($q) => $q
                ->where('name', 'ilike', "%{$s}%")
                ->orWhere('phone', 'ilike', "%{$s}%")))
            ->orderBy($s ? 'name' : 'created_at', $s ? 'asc' : 'desc')
            ->limit(30)
            ->get(['id', 'name', 'phone', 'points']);

        return response()->json($rows);
    }

    /** Tambah cepat dari kasir (JSON). */
    public function quick(Request $request)
    {
        $this->guard($request);
        $c = Customer::create($this->rules($request));

        return response()->json([...$c->only('id', 'name', 'phone'), 'points' => 0]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        Customer::create($this->rules($request));

        return back();
    }

    public function update(Request $request, string $id)
    {
        $this->guard($request);
        $customer = Customer::findOrFail($id);
        $customer->update($this->rules($request, $customer->id));

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        $this->guard($request, true);
        Customer::findOrFail($id)->delete();

        return back();
    }

    private function rules(Request $request, ?string $ignoreId = null): array
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $request->input('phone'));
        $request->merge(['phone' => $phone === '' ? null : $phone]);

        $businessId = $request->attributes->get('business')->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'address' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function guard(Request $request, bool $manager = false): void
    {
        $b = $request->attributes->get('business');
        abort_unless($b->canUse('customers'), 403, 'Fitur pelanggan tersedia mulai paket Tumbuh.');

        $roles = $manager ? ['owner', 'admin'] : ['owner', 'admin', 'kasir'];
        abort_unless(in_array($b->roleOf($request->user()), $roles, true), 403, 'Kamu tidak punya akses ke menu ini.');
    }
}
