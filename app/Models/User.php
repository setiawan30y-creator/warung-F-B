<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Pengguna aplikasi. Pengguna tenant terikat tenant_id; admin platform tidak punya tenant.
 * Catatan: model User sengaja tidak memakai global scope tenant agar proses login dapat
 * menemukan akun. Seluruh query pengguna di halaman tenant memakai scopeOfTenant().
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['tenant_id', 'name', 'email', 'phone', 'password', 'is_platform_admin',
        'all_branches', 'is_active', 'theme_mode', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    /** Selaras dengan default kolom di database. */
    protected $attributes = ['is_active' => true, 'all_branches' => false, 'is_platform_admin' => false];

    protected array $auditExclude = ['remember_token', 'last_login_at'];

    protected ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'all_branches' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOfTenant($query, Tenant|int $tenant)
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access')->withTimestamps();
    }

    public function isOwner(): bool
    {
        return $this->roles->contains(fn (Role $r) => $r->isOwner());
    }

    public function permissionKeys(): Collection
    {
        return $this->permissionCache ??= $this->roles()
            ->with('permissions:id,key')
            ->get()
            ->flatMap(fn (Role $r) => $r->permissions->pluck('key'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $key): bool
    {
        if (! $this->is_active || ! $this->tenant_id) {
            return false;
        }

        return $this->isOwner() || $this->permissionKeys()->contains($key);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** Pemilik atau pengguna dengan akses semua cabang. */
    public function canManageAllBranches(): bool
    {
        return $this->all_branches || $this->isOwner();
    }

    /** Cabang yang boleh diakses pengguna (selalu dalam tenant miliknya). */
    public function accessibleBranches()
    {
        $query = Branch::forTenant($this->tenant_id)->where('is_active', true)->orderBy('name');

        if (! $this->canManageAllBranches()) {
            $query->whereIn('id', $this->branches()->withoutGlobalScopes()->select('branches.id'));
        }

        return $query;
    }

    public function canAccessBranch(Branch|int|null $branch): bool
    {
        if ($branch === null) {
            return false;
        }
        $id = $branch instanceof Branch ? $branch->id : $branch;

        return $this->accessibleBranches()->whereKey($id)->exists();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
