<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'number', 'status', 'category', 'notes', 'variance_value',
        'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['variance_value' => 'float', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
