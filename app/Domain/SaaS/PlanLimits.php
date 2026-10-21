<?php

namespace App\Domain\SaaS;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;

/** Penegakan batas paket langganan (jumlah cabang & pengguna). */
class PlanLimits
{
    public function branchLimit(Tenant $tenant): ?int
    {
        return $tenant->subscription?->max_branches;
    }

    public function userLimit(Tenant $tenant): ?int
    {
        return $tenant->subscription?->max_users;
    }

    public function branchCount(Tenant $tenant): int
    {
        return Branch::forTenant($tenant)->count();
    }

    public function userCount(Tenant $tenant): int
    {
        return User::ofTenant($tenant)->where('is_active', true)->count();
    }

    public function canAddBranch(Tenant $tenant): bool
    {
        $limit = $this->branchLimit($tenant);

        return $limit === null || $this->branchCount($tenant) < $limit;
    }

    public function canAddUser(Tenant $tenant): bool
    {
        $limit = $this->userLimit($tenant);

        return $limit === null || $this->userCount($tenant) < $limit;
    }
}
