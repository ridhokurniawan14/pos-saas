<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Inertia;
use App\Support\Access;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'business' => function () use ($request) {
                $b = $request->attributes->get('business');

                if (! $b) {
                    return null;
                }

                $plan = $b->subscription?->plan;
                $plan?->loadMissing(['features', 'limits']);

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'status' => $b->status,
                    'plan' => ['code' => $plan?->code, 'name' => $plan?->name],
                    'days_left' => $b->daysLeft(),
                    'ends_at' => $b->subscription?->ends_at?->toDateString(),
                    'read_only' => $b->isReadOnly(),
                    'role' => $b->pivot?->role,
                    'permissions' => Access::areas($b->pivot?->role),
                    'features' => $plan?->features->where('enabled', true)->pluck('feature_key')->values(),
                    'limits' => $plan?->limits->pluck('value', 'key'),
                ];
            },
            // Dimuat belakangan: hanya saat dibutuhkan, tidak memperlambat render halaman
            'low_stock' => function () use ($request) {
                $b = $request->attributes->get('business');

                if (! $b || ! $b->canUse('stock_management')) {
                    return null;
                }

                $rows = \App\Models\Stock::query()
                    ->join('products', 'products.id', '=', 'stocks.product_id')
                    ->join('outlets', 'outlets.id', '=', 'stocks.outlet_id')
                    ->whereNull('products.deleted_at')
                    ->where('products.track_stock', true)
                    ->where('products.is_active', true)
                    ->where('products.min_stock', '>', 0)
                    ->whereColumn('stocks.qty', '<=', 'products.min_stock')
                    ->orderBy('stocks.qty')
                    ->limit(50)
                    ->get(['products.id', 'products.name', 'products.unit', 'products.min_stock', 'stocks.qty', 'outlets.name as outlet']);

                return ['count' => $rows->count(), 'items' => $rows->take(8)->values()];
            },
        ];
    }
}
