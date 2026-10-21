<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Isi kemasan per bahan, mis. 1 karung kentang = 25.000 g, 1 tray telur = 30 butir. */
class ItemUnitConversion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'inventory_item_id', 'unit_id', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
