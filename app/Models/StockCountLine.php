<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_count_id', 'inventory_item_id', 'system_quantity', 'counted_quantity',
        'posted_system_quantity', 'variance', 'unit_cost', 'variance_value'];

    protected function casts(): array
    {
        return ['system_quantity' => 'float', 'counted_quantity' => 'float', 'posted_system_quantity' => 'float',
            'variance' => 'float', 'unit_cost' => 'float', 'variance_value' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
