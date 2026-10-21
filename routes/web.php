<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\ManifestController;
use App\Livewire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Area tenant
    Route::middleware('tenant')->group(function () {
        Route::view('/langganan-tidak-aktif', 'subscription-inactive')->name('subscription.inactive');
        Route::livewire('/dashboard', Livewire\Dashboard::class)->name('dashboard');
        Route::post('/cabang-aktif', BranchSwitchController::class)->name('branch.switch');
        Route::post('/mode-tampilan', function (Request $request) {
            $data = $request->validate(['mode' => ['required', 'in:light,dark,system']]);
            $request->user()->update(['theme_mode' => $data['mode']]);

            return response()->noContent();
        })->name('theme-mode');
        Route::livewire('/akun', Livewire\AccountPage::class)->name('account');

        Route::prefix('pengaturan')->name('settings.')->group(function () {
            Route::livewire('/profil-perusahaan', Livewire\Settings\CompanyProfilePage::class)
                ->middleware('can:company.manage')->name('company');
            Route::livewire('/tema', Livewire\Settings\ThemePage::class)
                ->middleware('can:theme.manage')->name('theme');
            Route::livewire('/cabang', Livewire\Settings\BranchesPage::class)
                ->middleware('can:branches.view')->name('branches');
            Route::livewire('/pengguna', Livewire\Settings\UsersPage::class)
                ->middleware('can:users.view')->name('users');
            Route::livewire('/peran', Livewire\Settings\RolesPage::class)
                ->middleware('can:roles.manage')->name('roles');
            Route::livewire('/audit', Livewire\Settings\AuditLogPage::class)
                ->middleware('can:audit.view')->name('audit');
        });
    });

    // Area admin platform (pengelola SaaS)
    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::livewire('/tenant', Livewire\Platform\TenantsPage::class)->name('tenants');
    });
});
