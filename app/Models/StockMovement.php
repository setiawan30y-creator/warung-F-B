<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** Ledger pergerakan stok: append-only, sumber kebenaran saldo (Blueprint §13.1). */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPES = [
        'opening' => 'Saldo awal',
        'purchase_receipt' => 'Penerimaan pembelian',
        'production_consume' => 'Dipakai produksi',
        'production_output' => 'Hasil produksi',
        'sale' => 'Penjualan',
        'waste' => 'Susut/rusak',
        'adjustment' => 'Penyesuaian',
        'stock_count' => 'Stok opname',
    ];

    protected $fillable = ['tenant_id', 'branch_id', 'inventory_item_id', 'type', 'quantity', 'unit_cost',
        'total_cost', 'balance_after', 'reference_type', 'reference_id', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'float', 'total_cost' => 'float', 'balance_after' => 'float', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Pergerakan stok tidak dapat diubah. Gunakan penyesuaian.'));
        static::deleting(fn () => throw new LogicException('Pergerakan stok tidak dapat dihapus. Gunakan penyesuaian.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
