<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Resep menu (type menu) atau resep turunan/sub-resep (type sub) yang menghasilkan bahan racikan. */
class Recipe extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'name', 'menu_id', 'output_item_id', 'current_version_id'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_item_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'current_version_id');
    }

    public function isSub(): bool
    {
        return $this->type === 'sub';
    }
}
