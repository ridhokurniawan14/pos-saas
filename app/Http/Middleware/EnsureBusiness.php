<?php

namespace App\Http\Middleware;

use App\Actions\StartTrial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $business = $user->businesses()->first() ?? app(StartTrial::class)->handle($user);
        $request->attributes->set('business', $business);
        app()->instance('current_business_id', $business->id);

        if ($request->routeIs('logout', 'verification.*', 'onboarding.*', 'shifts.close')) {
            return $next($request);
        }

        if (! $business->onboarded_at) {
            return redirect()->route('onboarding.show');
        }

        if ($business->isReadOnly() && ! $request->isMethodSafe()) {
            abort(403, 'Masa trial/langganan habis. Silakan upgrade paket untuk melanjutkan.');
        }

        return $next($request);
    }
}
