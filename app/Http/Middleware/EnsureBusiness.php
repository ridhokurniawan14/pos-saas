<?php

namespace App\Http\Middleware;

use App\Actions\StartTrial;
use App\Models\Invitation;
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

        $businesses = $user->businesses()->get();

        if ($businesses->isEmpty() && $user->email_verified_at) {
            Invitation::acceptFor($user);
            $businesses = $user->businesses()->get();
        }

        if ($businesses->isEmpty()) {
            if (Invitation::pendingEmail($user->email)->exists()) {
                // menunggu verifikasi email: jangan buat toko trial baru
                if ($request->routeIs('logout', 'verification.*')) {
                    return $next($request);
                }

                return redirect()->route('verification.notice');
            }

            app(StartTrial::class)->handle($user);
            $businesses = $user->businesses()->get();
        }

        $sid = $request->session()->get('business_id');
        $business = $businesses->firstWhere('id', $sid) ?? $businesses->first();

        if ($business->id !== $sid) {
            $request->session()->put('business_id', $business->id);
        }

        $request->attributes->set('business', $business);
        $request->attributes->set('businesses', $businesses);
        app()->instance('current_business_id', $business->id);

        if ($request->routeIs('logout', 'verification.*', 'onboarding.*', 'shifts.close', 'business.switch')) {
            return $next($request);
        }

        if (! $business->onboarded_at) {
            if ($business->pivot?->role === 'owner') {
                return redirect()->route('onboarding.show');
            }

            abort(403, 'Pemilik toko belum menyelesaikan pengaturan awal.');
        }

        if ($business->isReadOnly() && ! $request->isMethodSafe()) {
            abort(403, 'Masa trial/langganan habis. Silakan upgrade paket untuk melanjutkan.');
        }

        return $next($request);
    }
}
