<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Bahan. type purchased = dibeli/langsung pakai; produced = hasil produksi sub-resep
 * (saus, bumbu tabur, sambal, marinasi, adonan, kaldu, topping).
 */
class InventoryItem extends Model
{
    use Auditable, BelongsToTenant;

    public const TYPES = ['purchased' => 'Bahan beli', 'produced' => 'Hasil racikan'];

    protected $fillable = ['tenant_id', 'code', 'name', 'category', 'type', 'base_unit_id', 'min_stock', 'last_cost', 'is_active'];

    protected function casts(): array
    {
        return ['min_stock' => 'float', 'last_cost' => 'float', 'is_active' => 'boolean'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /** Resep turunan yang menghasilkan bahan ini (khusus type produced). */
    public function producingRecipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'output_item_id');
    }

    public function isProduced(): bool
    {
        return $this->type === 'produced';
    }

    public function unitCode(): string
    {
        return $this->baseUnit?->code ?? '';
    }
}
