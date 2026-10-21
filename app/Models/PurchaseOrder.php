<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = ['draft' => 'Draf', 'ordered' => 'Dipesan', 'partial' => 'Diterima sebagian',
        'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];

    protected $fillable = ['tenant_id', 'branch_id', 'supplier_id', 'number', 'status', 'order_date',
        'expected_date', 'notes', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'total' => 'float'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partial'], true);
    }
}
