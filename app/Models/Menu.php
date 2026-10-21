<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Menu extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'menu_category_id', 'code', 'name', 'description', 'photo_path', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'float', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchMenu::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Harga dan status untuk satu cabang. */
    public function forBranch(?Branch $branch): array
    {
        $s = $branch ? $this->branchSettings->firstWhere('branch_id', $branch->id) : null;

        return [
            'price' => $s?->price ?? $this->price,
            'is_override' => $s?->price !== null,
            'is_available' => $s?->is_available ?? true,
            'is_sold_out' => $s?->is_sold_out ?? false,
        ];
    }
}
