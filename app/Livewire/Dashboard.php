<?php

namespace App\Livewire;

use App\Domain\SaaS\PlanLimits;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Concerns\TenantPage;
use App\Models\AuditLog;
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

        return view('livewire.dashboard', [
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
