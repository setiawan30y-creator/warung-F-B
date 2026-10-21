<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = [
        'trial' => 'Masa percobaan',
        'active' => 'Aktif',
        'past_due' => 'Lewat jatuh tempo',
        'suspended' => 'Dinonaktifkan',
        'cancelled' => 'Berhenti',
    ];

    protected $fillable = ['tenant_id', 'plan', 'status', 'trial_ends_at', 'current_period_ends_at',
        'max_branches', 'max_users', 'modules'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'modules' => 'array',
        ];
    }

    /** Tenant boleh memakai aplikasi (data tetap utuh walau tidak aktif). */
    public function allowsAccess(): bool
    {
        if ($this->status === 'trial') {
            return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
        }

        return in_array($this->status, ['active', 'past_due'], true);
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }

    public function planLabel(): string
    {
        return config("saas.plans.{$this->plan}.label", ucfirst($this->plan));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
