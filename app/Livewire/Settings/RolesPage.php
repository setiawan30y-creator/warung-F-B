<?php

namespace App\Livewire\Settings;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\PermissionCatalog;
use App\Livewire\Concerns\TenantPage;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Peran & izin')]
class RolesPage extends Component
{
    use TenantPage;

    public ?int $selectedId = null;

    public string $name = '';

    public string $description = '';

    public array $permissions = [];

    public bool $creating = false;

    public function mount(): void
    {
        $this->authorize('roles.manage');
        $this->select(Role::orderBy('id')->value('id'));
    }

    public function select(?int $id): void
    {
        $this->creating = false;
        $this->resetValidation();
        if (! $id) {
            return;
        }
        $role = Role::with('permissions:id,key')->findOrFail($id);
        $this->selectedId = $role->id;
        $this->name = $role->name;
        $this->description = (string) $role->description;
        $this->permissions = $role->permissions->pluck('key')->all();
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->selectedId = null;
        $this->name = '';
        $this->description = '';
        $this->permissions = [];
        $this->resetValidation();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('roles.manage');
        $role = $this->selectedId ? Role::findOrFail($this->selectedId) : null;
        abort_if($role?->isOwner(), 403, 'Peran Pemilik selalu memiliki semua izin.');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:60',
                Rule::unique('roles', 'name')->where('tenant_id', $this->tenant()->id)->ignore($role?->id)],
            'description' => 'nullable|string|max:160',
            'permissions' => 'array',
            'permissions.*' => Rule::in(PermissionCatalog::keys()),
        ], [], ['name' => 'nama peran']);

        $me = $this->user();
        if (! $me->isOwner()) {
            abort_if($role && $me->roles->contains('id', $role->id), 403, 'Anda tidak dapat mengubah peran yang Anda miliki sendiri.');
            $extra = array_diff($data['permissions'], $me->permissionKeys()->all());
            if ($extra !== []) {
                $this->addError('permissions', 'Anda hanya dapat memberikan izin yang Anda miliki sendiri.');

                return;
            }
        }

        if (! $role) {
            $slug = Str::slug($data['name']) ?: 'peran';
            $base = $slug;
            $i = 2;
            while (Role::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }
            $role = Role::create(['name' => $data['name'], 'slug' => $slug, 'description' => $data['description']]);
            $before = [];
        } else {
            $role->update(['name' => $data['name'], 'description' => $data['description']]);
            $before = $role->permissions()->pluck('key')->sort()->values()->all();
        }

        $role->permissions()->sync(Permission::whereIn('key', $data['permissions'])->pluck('id'));
        $after = collect($data['permissions'])->sort()->values()->all();
        if ($before !== $after) {
            $audit->log('role.permissions_changed', $role, ['permissions' => $before], ['permissions' => $after]);
        }

        $this->select($role->id);
        $this->dispatch('toast', message: 'Peran disimpan.');
    }

    public function delete(): void
    {
        $this->authorize('roles.manage');
        $role = Role::withCount('users')->findOrFail($this->selectedId);
        if ($role->is_system) {
            $this->dispatch('toast', message: 'Peran bawaan tidak dapat dihapus.');

            return;
        }
        if ($role->users_count > 0) {
            $this->dispatch('toast', message: "Peran masih dipakai {$role->users_count} pengguna. Pindahkan pengguna ke peran lain terlebih dahulu.");

            return;
        }
        $role->delete();
        $this->select(Role::orderBy('id')->value('id'));
        $this->dispatch('toast', message: 'Peran dihapus.');
    }

    public function render()
    {
        $selected = $this->selectedId ? Role::withCount('users')->find($this->selectedId) : null;

        return view('livewire.settings.roles', [
            'roles' => Role::withCount('users')->orderByDesc('is_system')->orderBy('id')->get(),
            'selected' => $selected,
            'groups' => PermissionCatalog::GROUPS,
            'locked' => $selected?->isOwner() ?? false,
        ]);
    }
}
