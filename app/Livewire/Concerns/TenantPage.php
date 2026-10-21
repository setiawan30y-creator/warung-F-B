<?php

namespace App\Livewire\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;

/**
 * Pengaman lapis kedua: memastikan konteks tenant selalu terisi saat komponen berjalan,
 * termasuk pada request update Livewire.
 */
trait TenantPage
{
    public function bootTenantPage(): void
    {
        $context = app(TenantContext::class);
        /** @var User|null $user */
        $user = auth()->user();

        abort_unless($user && $user->tenant_id && $user->is_active, 403);

        if (! $context->has()) {
            $context->set($user->tenant);
        }
        abort_unless($context->id() === $user->tenant_id, 403);
    }

    protected function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }

    protected function user(): User
    {
        return auth()->user();
    }
}
