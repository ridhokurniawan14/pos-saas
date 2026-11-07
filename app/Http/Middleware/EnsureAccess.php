<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccess
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $business = $request->attributes->get('business');
        $role = $business?->pivot?->role ?? ($business && $request->user() ? $business->roleOf($request->user()) : null);

        abort_unless(Access::can($role, $area), 403, 'Kamu tidak punya akses ke menu ini.');

        return $next($request);
    }
}
