<?php

namespace App\Domain\Tenancy;

use App\Models\Branch;
use App\Models\Tenant;
use Closure;

/**
 * Menyimpan tenant & cabang aktif untuk request/job yang sedang berjalan.
 * Semua model ber-trait BelongsToTenant otomatis difilter berdasarkan konteks ini.
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    protected ?Branch $branch = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        if ($tenant === null || ($this->branch && $this->branch->tenant_id !== $tenant->id)) {
            $this->branch = null;
        }
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function setBranch(?Branch $branch): void
    {
        if ($branch && $this->tenant && $branch->tenant_id !== $this->tenant->id) {
            throw new \LogicException('Cabang tidak termasuk tenant aktif.');
        }
        $this->branch = $branch;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    /**
     * Jalankan callback dalam konteks tenant tertentu (mis. di job antrean atau command),
     * lalu pulihkan konteks sebelumnya.
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        [$prevTenant, $prevBranch] = [$this->tenant, $this->branch];
        $this->set($tenant);
        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $prevTenant;
            $this->branch = $prevBranch;
        }
    }

    /** Jalankan tanpa konteks tenant (mis. admin platform membuat tenant baru), lalu pulihkan. */
    public function runWithoutTenant(Closure $callback): mixed
    {
        [$prevTenant, $prevBranch] = [$this->tenant, $this->branch];
        $this->tenant = null;
        $this->branch = null;
        try {
            return $callback();
        } finally {
            $this->tenant = $prevTenant;
            $this->branch = $prevBranch;
        }
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->branch = null;
    }
}
