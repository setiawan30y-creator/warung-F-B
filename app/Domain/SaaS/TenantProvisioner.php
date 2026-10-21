<?php

namespace App\Domain\SaaS;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Theme\ThemePalette;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat tenant baru beserta langganan, profil, tema, peran bawaan, cabang pertama, dan akun pemilik.
 */
class TenantProvisioner
{
    public function __construct(protected TenantContext $context) {}

    /**
     * @param  array{name:string, slug?:string, business_type?:string, plan:string,
     *               owner_name:string, owner_email:string, owner_password:string,
     *               branch_name?:string, branch_code?:string, trial?:bool}  $data
     */
    public function provision(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            self::syncPermissions();

            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'business_type' => $data['business_type'] ?? null,
                'status' => 'active',
            ]);

            return $this->context->run($tenant, function (Tenant $tenant) use ($data) {
                $plan = config("saas.plans.{$data['plan']}") ?? throw new \InvalidArgumentException('Paket tidak dikenal.');
                $trial = $data['trial'] ?? true;

                TenantSubscription::create([
                    'plan' => $data['plan'],
                    'status' => $trial ? 'trial' : 'active',
                    'trial_ends_at' => $trial ? now()->addDays(config('saas.trial_days')) : null,
                    'current_period_ends_at' => $trial ? null : now()->addMonth(),
                    'max_branches' => $plan['max_branches'],
                    'max_users' => $plan['max_users'],
                    'modules' => $plan['modules'],
                ]);

                CompanyProfile::create(['brand_name' => $tenant->name]);
                ThemeSetting::create(ThemePalette::defaults());

                $roles = $this->createDefaultRoles();

                $branch = Branch::create([
                    'code' => $data['branch_code'] ?? 'PST',
                    'name' => $data['branch_name'] ?? 'Cabang Utama',
                    'opening_hours' => Branch::defaultOpeningHours(),
                ]);

                $owner = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['owner_name'],
                    'email' => strtolower($data['owner_email']),
                    'password' => $data['owner_password'],
                    'all_branches' => true,
                ]);
                $owner->roles()->attach($roles['pemilik']->id);
                $owner->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

                return $tenant->fresh();
            });
        });
    }

    /** @return array<string, Role> */
    public function createDefaultRoles(): array
    {
        $permissionIds = Permission::pluck('id', 'key');
        $roles = [];
        foreach (PermissionCatalog::DEFAULT_ROLES as $slug => $def) {
            $role = Role::create([
                'name' => $def['name'],
                'slug' => $slug,
                'description' => $def['description'],
                'is_system' => true,
            ]);
            $keys = $def['permissions'] === '*' ? $permissionIds->keys()->all() : $def['permissions'];
            $role->permissions()->sync($permissionIds->only($keys)->values());
            $roles[$slug] = $role;
        }

        return $roles;
    }

    /** Selaraskan tabel permissions dengan katalog di kode. */
    public static function syncPermissions(): void
    {
        foreach (PermissionCatalog::all() as $key => $meta) {
            Permission::updateOrCreate(['key' => $key], $meta);
        }
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'usaha';
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
