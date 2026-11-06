<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Support\Locked;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        if (! $business->canUse('advanced_report')) {
            return Locked::page('Pengeluaran', 'Tumbuh');
        }
        $this->guard($request);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $base = Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q
                ->where('category', 'ilike', "%{$s}%")
                ->orWhere('note', 'ilike', "%{$s}%")));

        $expenses = (clone $base)->with('outlet:id,name')
            ->latest('expense_date')->latest('created_at')
            ->paginate(20)->withQueryString()
            ->through(fn($e) => [
                'id' => $e->id,
                'date' => $e->expense_date->format('d/m/Y'),
                'category' => $e->category,
                'amount' => $e->amount,
                'note' => $e->note,
                'outlet' => $e->outlet?->name,
            ]);

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'filters' => ['from' => $from, 'to' => $to, 'search' => $request->search],
            'total' => (int) (clone $base)->sum('amount'),
            'outlets' => $business->outlets()->orderBy('created_at')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $business = $request->attributes->get('business');
        $this->guard($request);

        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
            'outlet_id' => ['nullable', Rule::exists('outlets', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
        ]);

        Expense::create([...$data, 'created_by' => $request->user()->id]);

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        $this->guard($request);
        Expense::findOrFail($id)->delete();

        return back();
    }

    private function guard(Request $request): void
    {
        $b = $request->attributes->get('business');
        abort_unless($b->canUse('advanced_report'), 403, 'Pengeluaran tersedia mulai paket Tumbuh.');
        abort_unless($b->isManager($request->user()), 403, 'Hanya owner/admin yang boleh mengelola pengeluaran.');
    }
}
