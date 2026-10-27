<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\ReceiptPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:completed,void'],
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        $manager = $this->isManager($request);
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', $from);

        $base = Order::query()
            ->when(! $manager, fn ($q) => $q->where('cashier_id', $request->user()->id))
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('order_number', 'ilike', "%{$s}%"));

        $done = (clone $base)->where('status', 'completed');

        $orders = (clone $base)
            ->with(['cashier:id,name', 'payments:id,order_id,method'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($o) => [
                'id' => $o->id,
                'number' => $o->order_number,
                'time' => $o->created_at->format('d/m H:i'),
                'cashier' => $o->cashier?->name,
                'total' => $o->total,
                'status' => $o->status,
                'methods' => $o->payments->pluck('method')->unique()->values(),
            ]);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'filters' => ['from' => $from, 'to' => $to, 'status' => $request->status, 'search' => $request->search],
            'summary' => ['count' => (clone $done)->count(), 'total' => (int) (clone $done)->sum('total')],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $business = $request->attributes->get('business');
        $manager = $this->isManager($request);
        $order = Order::with(['items', 'payments', 'outlet', 'cashier'])->findOrFail($id);

        abort_unless($manager || $order->cashier_id === $request->user()->id, 403, 'Kamu tidak punya akses ke transaksi ini.');

        return response()->json([
            ...ReceiptPayload::make($order, $business),
            'can_void' => $manager && $order->status === 'completed',
        ]);
    }

    public function void(Request $request, string $id, StockService $stock)
    {
        abort_unless($this->isManager($request), 403, 'Hanya owner/admin yang boleh membatalkan transaksi.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        DB::transaction(function () use ($id, $data, $stock) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'completed') {
                throw ValidationException::withMessages(['void' => 'Transaksi ini sudah dibatalkan.']);
            }

            $order->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $data['reason'],
            ]);

            // Kembalikan stok persis sebesar yang dulu dikurangi saat penjualan
            $sales = StockMovement::where('ref_type', 'order')
                ->where('ref_id', $order->id)
                ->where('type', 'sale')
                ->orderBy('product_id')
                ->get();

            foreach ($sales as $m) {
                $product = Product::withTrashed()->find($m->product_id);
                if ($product) {
                    $stock->move($m->outlet_id, $product, 'return', -$m->qty, 'Batal ' . $order->order_number, 'order', $order->id);
                }
            }
        });

        return back();
    }

    private function isManager(Request $request): bool
    {
        $business = $request->attributes->get('business');

        $role = DB::table('business_user')
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->value('role');

        return in_array($role, ['owner', 'admin'], true);
    }
}