<?php

namespace App\Livewire\Settings;

use App\Domain\SaaS\PlanLimits;
use App\Livewire\Concerns\TenantPage;
use App\Models\Branch;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cabang')]
class BranchesPage extends Component
{
    use TenantPage;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->authorize('branches.view');
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'code' => '', 'name' => '', 'address' => '', 'city' => '', 'phone' => '', 'email' => '',
            'latitude' => '', 'longitude' => '', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'opening_hours' => Branch::defaultOpeningHours(),
        ];
        $this->resetValidation();
    }

    public function create(PlanLimits $limits): void
    {
        $this->authorize('branches.manage');
        if (! $limits->canAddBranch($this->tenant())) {
            $this->dispatch('toast', message: 'Batas cabang pada paket Anda sudah tercapai. Tingkatkan paket untuk menambah cabang.');

            return;
        }
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('branches.manage');
        $branch = $this->findBranch($id);
        $this->editingId = $branch->id;
        $this->resetForm();
        $this->form = array_merge($this->form, array_map(fn ($v) => $v ?? '', $branch->only([
            'code', 'name', 'address', 'city', 'phone', 'email', 'latitude', 'longitude', 'timezone',
        ])), [
            'is_active' => $branch->is_active,
            'opening_hours' => array_replace(Branch::defaultOpeningHours(), $branch->opening_hours ?? []),
        ]);
        $this->showForm = true;
    }

    protected function findBranch(int $id): Branch
    {
        $branch = Branch::findOrFail($id); // tenant scope → tenant lain tidak ditemukan
        abort_unless($this->user()->canManageAllBranches() || $this->user()->canAccessBranch($branch), 403);

        return $branch;
    }

    protected function rules(): array
    {
        return [
            'form.code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('branches', 'code')->where('tenant_id', $this->tenant()->id)->ignore($this->editingId)],
            'form.name' => 'required|string|max:120',
            'form.address' => 'nullable|string|max:255',
            'form.city' => 'nullable|string|max:80',
            'form.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'form.email' => 'nullable|email|max:160',
            'form.latitude' => 'nullable|numeric|between:-90,90|required_with:form.longitude',
            'form.longitude' => 'nullable|numeric|between:-180,180|required_with:form.latitude',
            'form.timezone' => 'required|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura',
            'form.is_active' => 'boolean',
            'form.opening_hours.*.open' => 'nullable|date_format:H:i',
            'form.opening_hours.*.close' => 'nullable|date_format:H:i',
            'form.opening_hours.*.closed' => 'boolean',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.code' => 'kode cabang', 'form.name' => 'nama cabang', 'form.latitude' => 'lintang',
            'form.longitude' => 'bujur', 'form.phone' => 'telepon', 'form.email' => 'email'];
    }

    public function save(PlanLimits $limits): void
    {
        $this->authorize('branches.manage');
        $data = $this->validate()['form'];
        $data['code'] = strtoupper($data['code']);
        foreach (['latitude', 'longitude', 'address', 'city', 'phone', 'email'] as $k) {
            $data[$k] = $data[$k] === '' ? null : $data[$k];
        }

        if ($this->editingId) {
            $branch = $this->findBranch($this->editingId);
            $branch->update($data);
            $message = 'Cabang diperbarui.';
        } else {
            abort_unless($limits->canAddBranch($this->tenant()), 403, 'Batas cabang paket tercapai.');
            $branch = Branch::create($data);
            $message = 'Cabang ditambahkan.';
        }

        $this->showForm = false;
        $this->dispatch('toast', message: $message);
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('branches.manage');
        $branch = $this->findBranch($id);
        if ($branch->is_active && Branch::where('is_active', true)->count() <= 1) {
            $this->dispatch('toast', message: 'Minimal harus ada satu cabang aktif.');

            return;
        }
        $branch->update(['is_active' => ! $branch->is_active]);
        $this->dispatch('toast', message: $branch->is_active ? 'Cabang diaktifkan.' : 'Cabang dinonaktifkan. Datanya tetap tersimpan.');
    }

    public function render(PlanLimits $limits)
    {
        $user = $this->user();
        $query = Branch::query()->withCount('users')->orderByDesc('is_active')->orderBy('name');
        if (! $user->canManageAllBranches()) {
            $query->whereIn('id', $user->accessibleBranches()->reorder()->select('branches.id'));
        }
        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
                ->orWhere('city', 'like', "%{$this->search}%"));
        }

        return view('livewire.settings.branches', [
            'branches' => $query->get(),
            'canManage' => $user->can('branches.manage'),
            'limit' => $limits->branchLimit($this->tenant()),
            'count' => $limits->branchCount($this->tenant()),
        ]);
    }
}
