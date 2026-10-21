<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Katalog izin global (bukan data tenant). */
class Permission extends Model
{
    protected $fillable = ['key', 'group', 'label'];
}
