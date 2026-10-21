<?php

namespace App\Livewire;

use App\Domain\SaaS\PlanLimits;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Concerns\TenantPage;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\WasteRecord;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beranda')]
class Dashboard extends Component
{
    use TenantPage;

    public function render(PlanLimits $limits)
    {
        $tenant = $this->tenant()->loadMissing(['subscription', 'companyProfile']);
        $branch = app(TenantContext::class)->branch();
        $profile = $tenant->companyProfile;
        $user = $this->user();

        $setup = [
            ['done' => (bool) $profile?->logo_path, 'label' => 'Unggah logo usaha', 'route' => 'settings.company', 'perm' => 'company.manage'],
            ['done' => (bool) ($profile?->phone || $profile?->whatsapp), 'label' => 'Lengkapi kontak dan alamat usaha', 'route' => 'settings.company', 'perm' => 'company.manage'],
            ['done' => (bool) $branch?->hasCoordinates(), 'label' => 'Isi lokasi cabang untuk widget cuaca dan peta', 'route' => 'settings.branches', 'perm' => 'branches.manage'],
            ['done' => $limits->userCount($tenant) > 1, 'label' => 'Tambahkan pengguna untuk tim Anda', 'route' => 'settings.users', 'perm' => 'users.manage'],
        ];

        $stockAlerts = collect();
        $pendingWaste = 0;
        if ($branch && $tenant->subscription?->hasModule('inventory') && $user->can('inventory.view')) {
            $balances = StockBalance::where('branch_id', $branch->id)->pluck('quantity', 'inventory_item_id');
            $stockAlerts = InventoryItem::with('baseUnit')->where('is_active', true)->where('min_stock', '>', 0)->get()
                ->map(fn ($i) => ['item' => $i, 'qty' => (float) ($balances[$i->id] ?? 0)])
                ->filter(fn ($r) => $r['qty'] <= $r['item']->min_stock)
                ->sortBy(fn ($r) => $r['qty'] / max($r['item']->min_stock, 0.0001))->values();
            $pendingWaste = WasteRecord::where('branch_id', $branch->id)->where('status', 'pending')->count();
        }

        return view('livewire.dashboard', [
            'stockAlerts' => $stockAlerts,
            'pendingWaste' => $pendingWaste,
            'canApproveWaste' => $user->can('inventory.approve'),
            'tenant' => $tenant,
            'profile' => $profile,
            'branch' => $branch,
            'subscription' => $tenant->subscription,
            'branchCount' => $limits->branchCount($tenant),
            'branchLimit' => $limits->branchLimit($tenant),
            'userCount' => $limits->userCount($tenant),
            'userLimit' => $limits->userLimit($tenant),
            'setup' => collect($setup)->filter(fn ($s) => $user->can($s['perm'])),
            'recent' => $user->can('audit.view')
                ? AuditLog::with('user:id,name')->latest('id')->limit(6)->get()
                : collect(),
            'hour' => now($branch->timezone ?? 'Asia/Jakarta')->hour,
        ]);
    }
}
