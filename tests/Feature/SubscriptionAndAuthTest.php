<?php

namespace Tests\Feature;

use App\Livewire\Platform\TenantsPage;
use App\Livewire\Settings\BranchesPage;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_plan_limits_branches(): void
    {
        $t = $this->makeTenant('Usaha Kecil', 'starter');

        Livewire::actingAs($this->owner($t))->test(BranchesPage::class)
            ->call('create')
            ->assertSet('showForm', false)
            ->assertDispatched('toast');

        $this->assertSame(1, Branch::forTenant($t)->count());
    }

    public function test_suspended_subscription_blocks_access_but_keeps_data(): void
    {
        $t = $this->makeTenant();
        TenantSubscription::forTenant($t)->update(['status' => 'suspended']);

        $this->actingAs($this->owner($t))->get(route('dashboard'))
            ->assertRedirect(route('subscription.inactive'));
        $this->actingAs($this->owner($t))->get(route('subscription.inactive'))->assertOk()->assertSee('Dinonaktifkan');
        $this->assertSame(1, Branch::forTenant($t)->count());
    }

    public function test_expired_trial_blocks_access(): void
    {
        $t = $this->makeTenant('Usaha Uji', 'starter', trial: true);
        TenantSubscription::forTenant($t)->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($this->owner($t))->get(route('dashboard'))->assertRedirect(route('subscription.inactive'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);
        $owner->update(['is_active' => false]);

        $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);
        $this->actingAs($owner);
        $owner->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited(): void
    {
        $t = $this->makeTenant();
        $email = $this->owner($t)->email;
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'salah']);
        }
        $this->post(route('login.store'), ['email' => $email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_platform_admin_area_is_protected(): void
    {
        $t = $this->makeTenant();
        $this->actingAs($this->owner($t))->get(route('platform.tenants'))->assertForbidden();
    }

    public function test_platform_admin_can_provision_tenant(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@platform.test', 'password' => 'rahasia123', 'is_platform_admin' => true]);

        Livewire::actingAs($admin)->test(TenantsPage::class)
            ->call('create')
            ->set('form.name', 'Kedai Baru')
            ->set('form.owner_name', 'Pemilik Kedai')
            ->set('form.owner_email', 'kedai@contoh.test')
            ->set('form.owner_password', 'rahasia123')
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::where('name', 'Kedai Baru')->firstOrFail();
        $this->assertSame(5, Role::forTenant($tenant)->count());
        $this->assertSame(1, Branch::forTenant($tenant)->count());
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'kedai@contoh.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }
}
