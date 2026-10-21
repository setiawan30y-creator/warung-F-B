<?php

namespace App\Providers;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Inventory\RecipeCostService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetTenantContext;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: konteks tenant direset untuk setiap request / job antrean.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(RecipeCostService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Middleware tenant ikut dijalankan ulang pada setiap update komponen Livewire.
        Livewire::addPersistentMiddleware([
            EnsureUserIsActive::class,
            SetTenantContext::class,
            EnsurePlatformAdmin::class,
        ]);

        $catalog = array_flip(PermissionCatalog::keys());

        Gate::before(function (User $user, string $ability) use ($catalog) {
            if (isset($catalog[$ability])) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }
}
