<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        $business = $request->attributes->get('business');

        if ($business->onboarded_at) {
            return redirect('/dashboard');
        }

        return Inertia::render('Onboarding');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'outlet_name' => ['required', 'string', 'max:100'],
        ]);

        $business = $request->attributes->get('business');

        $business->update([
            'name' => $data['business_name'],
            'onboarded_at' => now(),
        ]);

        $business->outlets()->oldest()->first()?->update(['name' => $data['outlet_name']]);

        return redirect('/dashboard');
    }
}
