<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Modul hanya bisa dibuka bila termasuk paket langganan tenant (Blueprint §4.2). */
class EnsureModuleEnabled
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $sub = $this->context->tenant()?->subscription;
        abort_unless($sub && $sub->hasModule($module), 403, 'Modul ini tidak termasuk paket langganan Anda.');

        return $next($request);
    }
}
