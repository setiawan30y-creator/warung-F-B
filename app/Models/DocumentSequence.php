<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'last_number'];
}
