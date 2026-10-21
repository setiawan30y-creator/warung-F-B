<?php

namespace App\Livewire\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\SaaS\PlanLimits;
use App\Livewire\Concerns\TenantPage;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pengguna')]
class UsersPage extends Component
{
    use TenantPage, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->authorize('users.view');
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'password' => '', 'is_active' => true,
            'all_branches' => false, 'roles' => [], 'branches' => []];
        $this->resetValidation();
    }

    /** Pengguna dalam tenant aktif saja; pengguna tenant lain menghasilkan 404. */
    protected function findUser(int $id): User
    {
        return User::ofTenant($this->tenant())->findOrFail($id);
    }

    protected function guardOwnerEdits(User $target): void
    {
        abort_if($target->isOwner() && ! $this->user()->isOwner(), 403, 'Hanya pemilik yang dapat mengubah akun pemilik.');
    }

    public function create(PlanLimits $limits): void
    {
        $this->authorize('users.manage');
        if (! $limits->canAddUser($this->tenant())) {
            $this->dispatch('toast', message: 'Batas pengguna pada paket Anda sudah tercapai.');

            return;
        }
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('users.manage');
        $user = $this->findUser($id);
        $this->guardOwnerEdits($user);
        $this->editingId = $user->id;
        $this->resetForm();
        $this->form = array_merge($this->form, [
            'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone ?? '',
            'is_active' => $user->is_active, 'all_branches' => $user->all_branches,
            'roles' => $user->roles->pluck('id')->map(fn ($i) => (string) $i)->all(),
            'branches' => $user->branches()->withoutGlobalScopes()->pluck('branches.id')->map(fn ($i) => (string) $i)->all(),
        ]);
        $this->showForm = true;
    }

    protected function rules(): array
    {
        $tenantId = $this->tenant()->id;

        return [
            'form.name' => 'required|string|max:120',
            'form.email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'form.password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'form.is_active' => 'boolean',
            'form.all_branches' => 'boolean',
            'form.roles' => 'required|array|min:1',
            'form.roles.*' => [Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
            'form.branches' => 'array',
            'form.branches.*' => [Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.name' => 'nama', 'form.email' => 'email', 'form.password' => 'kata sandi',
            'form.roles' => 'peran', 'form.branches' => 'cabang', 'form.phone' => 'telepon'];
    }

    public function save(PlanLimits $limits, AuditLogger $audit): void
    {
        $this->authorize('users.manage');
        $data = $this->validate()['form'];
        $me = $this->user();
        $tenant = $this->tenant();

        $roleIds = array_map('intval', $data['roles']);
        $branchIds = array_map('intval', $data['branches'] ?? []);
        $ownerRole = Role::where('slug', 'pemilik')->first();
        $grantsOwner = $ownerRole && in_array($ownerRole->id, $roleIds, true);

        if ($grantsOwner && ! $me->isOwner()) {
            $this->addError('form.roles', 'Hanya pemilik yang dapat memberikan peran Pemilik.');

            return;
        }
        if (! $me->isOwner()) {
            $granted = Role::with('permissions:id,key')->whereIn('id', $roleIds)->get()
                ->flatMap(fn ($r) => $r->permissions->pluck('key'))->unique();
            if ($granted->diff($me->permissionKeys())->isNotEmpty()) {
                $this->addError('form.roles', 'Anda hanya dapat memberikan peran dengan izin yang Anda miliki sendiri.');

                return;
            }
        }
        if (! $data['all_branches'] && ! $grantsOwner && $branchIds === []) {
            $this->addError('form.branches', 'Pilih minimal satu cabang atau beri akses semua cabang.');

            return;
        }

        $target = $this->editingId ? $this->findUser($this->editingId) : null;
        if ($target) {
            $this->guardOwnerEdits($target);
            if ($target->id === $me->id && ! $data['is_active']) {
                $this->addError('form.is_active', 'Anda tidak dapat menonaktifkan akun sendiri.');

                return;
            }
            if ($target->isOwner() && (! $grantsOwner || ! $data['is_active']) && $this->activeOwnerCount() <= 1) {
                $this->addError('form.roles', 'Usaha harus memiliki minimal satu pemilik aktif.');

                return;
            }
            if (! $target->is_active && $data['is_active'] && ! $limits->canAddUser($tenant)) {
                $this->addError('form.is_active', 'Batas pengguna aktif pada paket sudah tercapai.');

                return;
            }
        } elseif (! $limits->canAddUser($tenant)) {
            abort(403, 'Batas pengguna paket tercapai.');
        }

        DB::transaction(function () use ($data, $target, $roleIds, $branchIds, $tenant, $audit) {
            $attrs = [
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?: null,
                'is_active' => $data['is_active'],
                'all_branches' => $data['all_branches'],
            ];
            if (! empty($data['password'])) {
                $attrs['password'] = $data['password'];
            }

            $before = $target ? [
                'roles' => $target->roles()->pluck('name')->sort()->values()->all(),
                'branches' => $target->branches()->withoutGlobalScopes()->pluck('name')->sort()->values()->all(),
            ] : null;

            $user = $target ?? new User(['tenant_id' => $tenant->id]);
            $user->fill($attrs);
            $user->tenant_id = $tenant->id;
            $user->save();

            $user->roles()->sync($roleIds);
            $user->branches()->syncWithPivotValues($branchIds, ['tenant_id' => $tenant->id]);

            $after = [
                'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
                'branches' => $user->branches()->withoutGlobalScopes()->pluck('name')->sort()->values()->all(),
            ];
            if ($before !== $after) {
                $audit->log('user.access_changed', $user, $before, $after, 'Peran/akses cabang diubah');
            }
        });

        $this->showForm = false;
        $this->dispatch('toast', message: $target ? 'Pengguna diperbarui.' : 'Pengguna ditambahkan.');
    }

    protected function activeOwnerCount(): int
    {
        return User::ofTenant($this->tenant())->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'pemilik'))->count();
    }

    public function render(PlanLimits $limits)
    {
        $users = User::ofTenant($this->tenant())
            ->with(['roles:id,name,slug'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20);

        return view('livewire.settings.users', [
            'users' => $users,
            'roles' => Role::orderByDesc('is_system')->orderBy('id')->get(),
            'branches' => Branch::orderBy('name')->get(['id', 'name', 'code', 'is_active']),
            'canManage' => $this->user()->can('users.manage'),
            'count' => $limits->userCount($this->tenant()),
            'limit' => $limits->userLimit($this->tenant()),
        ]);
    }
}
