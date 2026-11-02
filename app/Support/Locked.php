<?php

namespace App\Support;

use Inertia\Inertia;

class Locked
{
    public static function page(string $feature, string $plan = 'Rintis')
    {
        return Inertia::render('Locked', ['feature' => $feature, 'plan' => $plan]);
    }
}
