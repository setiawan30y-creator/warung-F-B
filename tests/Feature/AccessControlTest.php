<?php

namespace Tests\Feature;

use App\Livewire\Settings\RolesPage;
use App\Livewire\Settings\UsersPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Kriteria penerimaan #2: pengguna hanya dapat bertindak sesuai peran dan akses cabang. */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_only_sees_assigned_branch(): void
    {
        $t = $this->makeTenant();
        $pusat = $this->firstBranch($t);
        $timur = $this->addBranch($t, 'TMR', 'Cabang Timur');
        $kasir = $this->member($t, 'kasir', [$timur]);

        $this->assertSame([$timur->id], $kasir->accessibleBranches()->pluck('id')->all());
        $this->assertFalse($kasir->canAccessBranch($pusat));

        $this->actingAs($kasir)
            ->post(route('branch.switch'), ['branch_id' => $pusat->id])
            ->assertForbidden();

        $this->actingAs($kasir)->get(route('dashboard'))->assertOk()->assertSee('Cabang Timur');
    }

    public function test_session_cannot_force_unassigned_branch(): void
    {
        $t = $this->makeTenant();
        $pusat = $this->firstBranch($t);
        $timur = $this->addBranch($t, 'TMR', 'Cabang Timur');
        $kasir = $this->member($t, 'kasir', [$timur]);

        $this->actingAs($kasir)->withSession(['active_branch_id' => $pusat->id])
            ->get(route('dashboard'))->assertOk();

        $this->assertSame($timur->id, session('active_branch_id'));
    }

    public function test_settings_pages_require_permission(): void
    {
        $t = $this->makeTenant();
        $kasir = $this->member($t, 'kasir', [$this->firstBranch($t)]);

        foreach (['settings.users', 'settings.roles', 'settings.company', 'settings.theme', 'settings.audit'] as $route) {
            $this->actingAs($kasir)->get(route($route))->assertForbidden();
        }
        foreach (['settings.users', 'settings.roles', 'settings.company', 'settings.theme', 'settings.audit', 'settings.branches'] as $route) {
            $this->actingAs($this->owner($t))->get(route($route))->assertOk();
        }
    }

    public function test_livewire_actions_check_permission_server_side(): void
    {
        $t = $this->makeTenant();
        $manajer = $this->member($t, 'manajer', allBranches: true); // users.view tetapi tidak users.manage

        Livewire::actingAs($manajer)->test(UsersPage::class)->call('create')->assertForbidden();
    }

    public function test_non_owner_cannot_grant_owner_role_or_higher_permissions(): void
    {
        $t = $this->makeTenant();
        $admin = $this->member($t, 'manajer', allBranches: true);
        // Beri manajer izin kelola pengguna (tanpa roles.manage).
        $role = Role::forTenant($t)->where('slug', 'manajer')->first();
        $role->permissions()->attach(Permission::where('key', 'users.manage')->value('id'));
        $owner = Role::forTenant($t)->where('slug', 'pemilik')->first();

        Livewire::actingAs($admin)->test(UsersPage::class)
            ->call('create')
            ->set('form.name', 'Akun Baru')
            ->set('form.email', 'baru@contoh.test')
            ->set('form.password', 'rahasia123')
            ->set('form.all_branches', true)
            ->set('form.roles', [(string) $owner->id])
            ->call('save')
            ->assertHasErrors('form.roles');

        $this->assertDatabaseMissing('users', ['email' => 'baru@contoh.test']);
    }

    public function test_non_owner_cannot_edit_owner_account(): void
    {
        $t = $this->makeTenant();
        $admin = $this->member($t, 'manajer', allBranches: true);
        Role::forTenant($t)->where('slug', 'manajer')->first()
            ->permissions()->attach(Permission::where('key', 'users.manage')->value('id'));

        Livewire::actingAs($admin)->test(UsersPage::class)
            ->call('edit', $this->owner($t)->id)
            ->assertForbidden();
    }

    public function test_owner_role_permissions_are_locked(): void
    {
        $t = $this->makeTenant();
        $ownerRole = Role::forTenant($t)->where('slug', 'pemilik')->first();

        Livewire::actingAs($this->owner($t))->test(RolesPage::class)
            ->call('select', $ownerRole->id)
            ->set('permissions', [])
            ->call('save')
            ->assertForbidden();
    }

    public function test_last_owner_cannot_be_deactivated(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);

        Livewire::actingAs($owner)->test(UsersPage::class)
            ->call('edit', $owner->id)
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasErrors('form.is_active');

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_owner_can_create_cashier_with_branch_access(): void
    {
        $t = $this->makeTenant();
        $timur = $this->addBranch($t, 'TMR', 'Cabang Timur');
        $kasirRole = Role::forTenant($t)->where('slug', 'kasir')->first();

        Livewire::actingAs($this->owner($t))->test(UsersPage::class)
            ->call('create')
            ->set('form.name', 'Kasir Baru')
            ->set('form.email', 'Kasir.Baru@Contoh.test')
            ->set('form.password', 'rahasia123')
            ->set('form.roles', [(string) $kasirRole->id])
            ->set('form.branches', [(string) $timur->id])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'kasir.baru@contoh.test')->firstOrFail();
        $this->assertSame($t->id, $user->tenant_id);
        $this->assertSame([$timur->id], $user->accessibleBranches()->pluck('id')->all());
    }
}
