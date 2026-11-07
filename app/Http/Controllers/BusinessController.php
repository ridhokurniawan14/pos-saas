<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['business_id' => ['required', 'uuid']]);

        abort_unless(
            $request->user()->businesses()->where('businesses.id', $data['business_id'])->exists(),
            403,
            'Kamu bukan anggota toko ini.',
        );

        $request->session()->put('business_id', $data['business_id']);

        return redirect('/dashboard');
    }
}
