<?php

namespace App\Livewire\Platform;

use App\Domain\SaaS\TenantProvisioner;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pengelolaan tenant oleh admin platform (Blueprint §4.2). */
#[Layout('layouts.platform')]
#[Title('Tenant')]
class TenantsPage extends Component
{
    public bool $showForm = false;

    public array $form = [];

    public ?int $subscriptionId = null;

    public array $sub = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'business_type' => '', 'plan' => 'starter', 'trial' => true,
            'owner_name' => '', 'owner_email' => '', 'owner_password' => '', 'branch_name' => 'Cabang Utama', 'branch_code' => 'PST'];
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(TenantProvisioner $provisioner): void
    {
        abort_unless(auth()->user()->is_platform_admin, 403);
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.business_type' => 'nullable|string|max:60',
            'form.plan' => ['required', Rule::in(array_keys(config('saas.plans')))],
            'form.trial' => 'boolean',
            'form.owner_name' => 'required|string|max:120',
            'form.owner_email' => 'required|email|max:160|unique:users,email',
            'form.owner_password' => ['required', Password::min(8)->letters()->numbers()],
            'form.branch_name' => 'required|string|max:120',
            'form.branch_code' => 'required|alpha_dash|max:20',
        ], [], ['form.name' => 'nama usaha', 'form.owner_email' => 'email pemilik', 'form.owner_password' => 'kata sandi', 'form.owner_name' => 'nama pemilik'])['form'];

        $data['branch_code'] = strtoupper($data['branch_code']);
        $tenant = $provisioner->provision($data);
        $this->showForm = false;
        $this->dispatch('toast', message: "Tenant {$tenant->name} dibuat.");
    }

    public function editSubscription(int $tenantId): void
    {
        $s = TenantSubscription::where('tenant_id', $tenantId)->firstOrFail();
        $this->subscriptionId = $s->id;
        $this->sub = [
            'plan' => $s->plan, 'status' => $s->status,
            'trial_ends_at' => $s->trial_ends_at?->format('Y-m-d') ?? '',
            'current_period_ends_at' => $s->current_period_ends_at?->format('Y-m-d') ?? '',
        ];
    }

    public function saveSubscription(): void
    {
        abort_unless(auth()->user()->is_platform_admin, 403);
        $data = $this->validate([
            'sub.plan' => ['required', Rule::in(array_keys(config('saas.plans')))],
            'sub.status' => ['required', Rule::in(array_keys(TenantSubscription::STATUSES))],
            'sub.trial_ends_at' => 'nullable|date',
            'sub.current_period_ends_at' => 'nullable|date',
        ])['sub'];
        $s = TenantSubscription::findOrFail($this->subscriptionId);
        $plan = config("saas.plans.{$data['plan']}");
        $s->update([
            'plan' => $data['plan'],
            'status' => $data['status'],
            'trial_ends_at' => $data['trial_ends_at'] ?: null,
            'current_period_ends_at' => $data['current_period_ends_at'] ?: null,
            'max_branches' => $plan['max_branches'],
            'max_users' => $plan['max_users'],
            'modules' => $plan['modules'],
        ]);
        $this->subscriptionId = null;
        $this->dispatch('toast', message: 'Langganan diperbarui. Data tenant tidak diubah.');
    }

    public function toggleTenant(int $id): void
    {
        abort_unless(auth()->user()->is_platform_admin, 403);
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => $tenant->isActive() ? 'suspended' : 'active']);
        $this->dispatch('toast', message: $tenant->isActive() ? 'Tenant diaktifkan.' : 'Tenant dinonaktifkan. Datanya tetap tersimpan.');
    }

    public function render()
    {
        return view('livewire.platform.tenants', [
            'tenants' => Tenant::with('subscription')->withCount(['branches', 'users'])->latest('id')->get(),
            'plans' => config('saas.plans'),
            'statuses' => TenantSubscription::STATUSES,
        ]);
    }
}
