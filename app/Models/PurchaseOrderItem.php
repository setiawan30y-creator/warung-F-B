<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'inventory_item_id', 'quantity', 'unit_id', 'unit_price', 'base_quantity', 'received_base_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'float', 'base_quantity' => 'float', 'received_base_quantity' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function remainingBase(): float
    {
        return max(0, round($this->base_quantity - $this->received_base_quantity, 4));
    }

    /** Harga per satuan dasar. */
    public function baseUnitCost(): float
    {
        return $this->base_quantity > 0 ? ($this->quantity * $this->unit_price) / $this->base_quantity : 0;
    }

    public function lineTotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
