<?php

namespace Tests;

use App\Domain\SaaS\TenantProvisioner;
use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function makeTenant(string $name = 'Usaha A', string $plan = 'growth', bool $trial = false): Tenant
    {
        $tenant = app(TenantProvisioner::class)->provision([
            'name' => $name,
            'plan' => $plan,
            'trial' => $trial,
            'owner_name' => 'Pemilik '.$name,
            'owner_email' => Str::slug($name).'-'.Str::random(5).'@contoh.test',
            'owner_password' => 'rahasia123',
        ]);
        app(TenantContext::class)->forget();

        return $tenant;
    }

    protected function owner(Tenant $tenant): User
    {
        return User::ofTenant($tenant)
            ->whereHas('roles', fn ($q) => $q->withoutGlobalScopes()->where('slug', 'pemilik'))
            ->firstOrFail();
    }

    protected function firstBranch(Tenant $tenant): Branch
    {
        return Branch::forTenant($tenant)->orderBy('id')->firstOrFail();
    }

    /** Buat pengguna dengan peran tertentu dan akses ke cabang yang diberikan. */
    protected function member(Tenant $tenant, string $roleSlug, array $branches = [], bool $allBranches = false): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst($roleSlug).' '.Str::random(4),
            'email' => $roleSlug.'-'.Str::random(6).'@contoh.test',
            'password' => 'rahasia123',
            'all_branches' => $allBranches,
        ]);
        $user->roles()->attach(Role::forTenant($tenant)->where('slug', $roleSlug)->value('id'));
        foreach ($branches as $b) {
            $user->branches()->attach($b->id, ['tenant_id' => $tenant->id]);
        }
        app(TenantContext::class)->forget();

        return $user;
    }

    protected function addBranch(Tenant $tenant, string $code, string $name): Branch
    {
        return app(TenantContext::class)->run($tenant, fn () => Branch::create([
            'code' => $code, 'name' => $name, 'opening_hours' => Branch::defaultOpeningHours(),
        ]));
    }
}
