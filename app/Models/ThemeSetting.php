<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'mode', 'preset', 'font', 'radius', 'colors_light', 'colors_dark'];

    protected function casts(): array
    {
        return ['colors_light' => 'array', 'colors_dark' => 'array'];
    }
}
