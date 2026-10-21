<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saldo per cabang (snapshot). Selalu dapat direkonsiliasi dengan stock_movements. */
class StockBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'inventory_item_id', 'quantity', 'avg_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'avg_cost' => 'float'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
