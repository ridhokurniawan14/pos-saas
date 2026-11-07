<?php

namespace App\Listeners;

use App\Models\Invitation;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;

class AcceptInvitations
{
    public function handle(Login|Verified $event): void
    {
        $user = $event->user;

        if ($user->email_verified_at) {
            Invitation::acceptFor($user);
        }
    }
}
