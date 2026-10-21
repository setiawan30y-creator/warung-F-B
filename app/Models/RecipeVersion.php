<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Versi resep bersifat tetap agar HPP historis tidak berubah (Blueprint §12.3). */
class RecipeVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'recipe_id', 'version', 'yield_quantity', 'effective_from', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['yield_quantity' => 'float', 'effective_from' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Versi resep tidak dapat diubah. Simpan sebagai versi baru.'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
