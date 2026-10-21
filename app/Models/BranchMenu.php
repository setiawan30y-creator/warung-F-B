<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BranchMenu extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'branch_menu';

    protected $fillable = ['tenant_id', 'branch_id', 'menu_id', 'price', 'is_available', 'is_sold_out'];

    protected function casts(): array
    {
        return ['price' => 'float', 'is_available' => 'boolean', 'is_sold_out' => 'boolean'];
    }
}
