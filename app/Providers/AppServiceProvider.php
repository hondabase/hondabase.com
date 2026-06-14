<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
        });

        // Staff (and the owner) manage articles: review/approve edits, apply their own without
        // a separate approver, and revert applied changes. The approval gate for everyone else.
        Gate::define('manage-articles', fn (User $user) => $user->isStaff());

        // Granting/revoking the staff role is owner-only: staff manage articles, but only the
        // instance owner decides who is staff (mirrors the hondabase:staff artisan command).
        Gate::define('manage-staff', fn (User $user) => $user->isOwner());
    }
}
