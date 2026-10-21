<?php

namespace App\Domain\Audit;

use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat audit terpusat (Blueprint §23).
 * Menyimpan pelaku, waktu server, objek, alasan, dan nilai sebelum/sesudah.
 */
class AuditLogger
{
    /** Atribut yang tidak pernah disimpan ke log. */
    public const REDACTED = ['password', 'remember_token'];

    public function __construct(protected TenantContext $context) {}

    public function log(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        ?string $reason = null,
        ?int $tenantId = null,
        ?int $userId = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'tenant_id' => $tenantId
                ?? ($subject instanceof Tenant ? $subject->getKey() : $subject?->getAttribute('tenant_id'))
                ?? $this->context->id(),
            'branch_id' => $this->context->branchId(),
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old ? $this->redact($old) : null,
            'new_values' => $new ? $this->redact($new) : null,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    protected function redact(array $values): array
    {
        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '••••••';
            }
        }

        return $values;
    }
}
