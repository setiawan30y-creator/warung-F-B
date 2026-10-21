<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = ['draft' => 'Draf', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'];

    protected $fillable = ['tenant_id', 'branch_id', 'recipe_version_id', 'output_item_id', 'number', 'status',
        'planned_output', 'expected_output', 'actual_output', 'total_cost', 'unit_cost', 'variance_reason',
        'produced_at', 'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['planned_output' => 'float', 'expected_output' => 'float', 'actual_output' => 'float',
            'total_cost' => 'float', 'unit_cost' => 'float', 'produced_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_item_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Selisih hasil aktual terhadap hasil yang diharapkan dari konsumsi aktual. */
    public function yieldVariance(): ?float
    {
        return $this->actual_output !== null && $this->expected_output !== null
            ? round($this->actual_output - $this->expected_output, 4) : null;
    }
}
