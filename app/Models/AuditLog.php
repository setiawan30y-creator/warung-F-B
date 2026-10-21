<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Log audit bersifat append-only: tidak dapat diubah atau dihapus lewat aplikasi (Blueprint §23.1).
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'branch_id', 'user_id', 'event', 'auditable_type', 'auditable_id',
        'description', 'old_values', 'new_values', 'reason', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log audit tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Log audit tidak dapat dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScopes();
    }

    public function eventLabel(): string
    {
        // Kunci event mengandung titik, jadi dibaca langsung dari array terjemahan.
        $labels = trans('audit.events');

        return is_array($labels) && isset($labels[$this->event]) ? $labels[$this->event] : $this->event;
    }
}
