<?php

namespace App\Models;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satuan. tenant_id null = satuan standar milik platform (gram, kg, liter, pcs, karton, dst.).
 * Satuan kemasan (dimension = pack) tidak punya faktor tetap; isinya diatur per bahan.
 */
class Unit extends Model
{
    public const DIMENSIONS = ['mass' => 'Berat', 'volume' => 'Volume', 'count' => 'Jumlah', 'pack' => 'Kemasan'];

    protected $fillable = ['tenant_id', 'code', 'name', 'dimension', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    /** Satuan standar + satuan milik tenant aktif. */
    public function scopeAvailable(Builder $q): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $q->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)));
    }

    /** Satuan yang boleh menjadi satuan dasar bahan. */
    public function scopeBase(Builder $q): Builder
    {
        return $q->whereIn('code', ['g', 'ml', 'pcs', 'butir', 'buah', 'porsi', 'tusuk', 'potong', 'ekor']);
    }
}
