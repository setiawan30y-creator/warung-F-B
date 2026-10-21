<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteRecord extends Model
{
    use Auditable, BelongsToTenant;

    public const REASONS = [
        'rusak' => 'Rusak', 'basi' => 'Basi', 'kedaluwarsa' => 'Kedaluwarsa', 'tumpah' => 'Tumpah/jatuh',
        'gagal_produksi' => 'Gagal produksi', 'lainnya' => 'Lainnya',
    ];

    public const STATUSES = ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

    protected $fillable = ['tenant_id', 'branch_id', 'inventory_item_id', 'quantity', 'unit_id', 'base_quantity',
        'reason', 'notes', 'estimated_value', 'status', 'created_by', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'base_quantity' => 'float', 'estimated_value' => 'float', 'decided_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
