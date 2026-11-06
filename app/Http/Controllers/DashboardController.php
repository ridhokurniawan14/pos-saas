<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReceivablePayment;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $svc)
    {
        $business = $request->attributes->get('business');
        $user = $request->user();
        $manager = $business->isManager($user);
        $advanced = $manager && $business->canUse('advanced_report');
        $cashier = $manager ? null : $user->id; // kasir hanya melihat transaksinya sendiri

        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $todayReport = $svc->sales($today, $today->copy()->endOfDay(), null, $cashier, $advanced);
        $week = $svc->sales($today->copy()->subDays(6), $today->copy()->endOfDay(), null, $cashier, false);
        $yReport = $svc->sales($yesterday, $yesterday->copy()->endOfDay(), null, $cashier, false);

        $receivable = null;
        if ($manager && $business->canUse('receivable')) {
            $due = (int) Order::where('status', 'completed')->sum('due_amount');
            $paid = (int) ReceivablePayment::whereNull('reversed_at')->sum('amount');
            $receivable = max(0, $due - $paid);
        }

        $recent = Order::with('cashier:id,name')
            ->when($cashier, fn($q) => $q->where('cashier_id', $cashier))
            ->latest()->limit(6)->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'number' => $o->order_number,
                'time' => $o->created_at->timezone($business->timezone)->format('d/m H:i'),
                'total' => $o->total,
                'status' => $o->status,
                'cashier' => $o->cashier?->name,
            ])->values();

        return Inertia::render('Dashboard', [
            'today' => $todayReport['summary'],
            'yesterday_revenue' => $yReport['summary']['revenue'],
            'week' => $week['days'],
            'recent' => $recent,
            'receivable' => $receivable,
            'manager' => $manager,
            'advanced' => $advanced,
        ]);
    }
}
