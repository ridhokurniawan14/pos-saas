<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        \Illuminate\Support\Facades\Event::listen(
            [\Illuminate\Auth\Events\Login::class, \Illuminate\Auth\Events\Verified::class],
            \App\Listeners\AcceptInvitations::class,
        );

        \App\Models\User::created(function (\App\Models\User $user) {
            // Orang yang diundang bergabung ke toko yang mengundang, bukan membuat toko trial baru
            if (\App\Models\Invitation::pendingEmail($user->email)->exists()) {
                return;
            }

            app(\App\Actions\StartTrial::class)->handle($user);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }
}
