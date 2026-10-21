<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionConsumption extends Model
{
    public $timestamps = false;

    protected $fillable = ['production_batch_id', 'inventory_item_id', 'planned_quantity', 'actual_quantity', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'float', 'actual_quantity' => 'float', 'unit_cost' => 'float', 'total_cost' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
