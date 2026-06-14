<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Support\Facades\Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
        });

        // Staff (and the owner) manage articles: review/approve edits, apply their own without
        // a separate approver, and revert applied changes. The approval gate for everyone else.
        \Illuminate\Support\Facades\Gate::define('manage-articles', fn (\App\Models\User $user) => $user->isStaff());

        // Granting/revoking the staff role is owner-only: staff manage articles, but only the
        // instance owner decides who is staff (mirrors the hondabase:staff artisan command).
        \Illuminate\Support\Facades\Gate::define('manage-staff', fn (\App\Models\User $user) => $user->isOwner());
    }
}
