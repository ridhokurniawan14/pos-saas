<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $google = Socialite::driver('google')->user();

        $user = User::where('google_id', $google->getId())
            ->orWhere('email', $google->getEmail())
            ->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $google->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = new User();
            $user->forceFill([
                'name' => $google->getName(),
                'email' => $google->getEmail(),
                'google_id' => $google->getId(),
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::login($user, true);

        return redirect()->intended('/dashboard');
    }
}
