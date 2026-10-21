<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pasang pada setiap model data tenant.
 * - Query otomatis difilter ke tenant aktif.
 * - tenant_id terisi otomatis saat membuat data.
 * - Mencegah penyimpanan data ke tenant lain.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);
            if (! $model->tenant_id && $context->has()) {
                $model->tenant_id = $context->id();
            }
            static::guardTenant($model);
        });

        static::updating(fn ($model) => static::guardTenant($model));
        static::deleting(fn ($model) => static::guardTenant($model));
    }

    protected static function guardTenant($model): void
    {
        $context = app(TenantContext::class);

        if ($context->has() && (int) $model->tenant_id !== (int) $context->id()) {
            throw new TenantViolationException('Akses lintas tenant ditolak.');
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->qualifyColumn('tenant_id'), $tenant instanceof Tenant ? $tenant->id : $tenant);
    }
}
