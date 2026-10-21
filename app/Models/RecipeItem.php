<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipe_version_id', 'inventory_item_id', 'quantity', 'unit_id', 'base_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'base_quantity' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
