<?php

namespace App\Livewire\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
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

        // Pulihkan cabang aktif bila belum ditetapkan (mis. request tanpa middleware tenant).
        if (! $context->branch()) {
            $id = session('active_branch_id');
            $branch = $id ? \App\Models\Branch::whereKey($id)->where('is_active', true)->first() : null;
            if (! $branch || ! $user->canAccessBranch($branch)) {
                $branch = $user->accessibleBranches()->first();
            }
            $context->setBranch($branch);
        }
    }

    protected function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }

    /** Cabang aktif; operasi stok selalu terjadi di cabang ini. */
    protected function branch(): Branch
    {
        $branch = app(TenantContext::class)->branch();
        abort_unless($branch && $this->user()->canAccessBranch($branch), 403, 'Pilih cabang aktif terlebih dahulu.');

        return $branch;
    }

    protected function hasModule(string $module): bool
    {
        return (bool) $this->tenant()->subscription?->hasModule($module);
    }

    protected function user(): User
    {
        return auth()->user();
    }
}
