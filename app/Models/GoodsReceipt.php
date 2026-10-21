<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'purchase_order_id', 'number', 'received_at', 'notes', 'total', 'received_by'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'total' => 'float'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
