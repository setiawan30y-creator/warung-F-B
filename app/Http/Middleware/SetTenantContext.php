<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan tenant & cabang aktif dari pengguna yang login.
 * Hak akses cabang diperiksa di server (Blueprint §4.1), bukan hanya disembunyikan di UI.
 */
class SetTenantContext
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->tenant_id) {
            return $user->is_platform_admin ? redirect()->route('platform.tenants') : abort(403);
        }

        $tenant = $user->tenant()->with(['subscription', 'companyProfile', 'themeSetting'])->first();
        $this->context->set($tenant);

        $blocked = ! $tenant->isActive() || ! $tenant->subscription?->allowsAccess();
        if ($blocked && ! $request->routeIs('subscription.inactive', 'logout')) {
            return redirect()->route('subscription.inactive');
        }

        $branch = $this->resolveBranch($request, $user);
        $this->context->setBranch($branch);

        View::share('currentTenant', $tenant);
        View::share('currentBranch', $branch);

        return $next($request);
    }

    protected function resolveBranch(Request $request, $user): ?Branch
    {
        $id = $request->session()->get('active_branch_id');
        $branch = $id ? Branch::whereKey($id)->where('is_active', true)->first() : null;

        if (! $branch || ! $user->canAccessBranch($branch)) {
            $branch = $user->accessibleBranches()->first();
            $request->session()->put('active_branch_id', $branch?->id);
        }

        return $branch;
    }
}
