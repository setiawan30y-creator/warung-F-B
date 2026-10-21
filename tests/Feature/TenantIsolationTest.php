<?php

namespace Tests\Feature;

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantViolationException;
use App\Livewire\Settings\BranchesPage;
use App\Livewire\Settings\CompanyProfilePage;
use App\Livewire\Settings\UsersPage;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Kriteria penerimaan #1 dan #12: Tenant A tidak dapat membaca atau mengubah data Tenant B. */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_active_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $branchB = $this->firstBranch($b);

        app(TenantContext::class)->run($a, function () use ($a, $branchB) {
            $this->assertSame([$a->id], Branch::pluck('tenant_id')->unique()->values()->all());
            $this->assertNull(Branch::find($branchB->id));
            $this->assertSame($a->id, CompanyProfile::sole()->tenant_id);
            $this->assertTrue(Role::get()->every(fn ($r) => $r->tenant_id === $a->id));
            $this->assertTrue(AuditLog::get()->every(fn ($l) => $l->tenant_id === $a->id));
        });
    }

    public function test_new_records_get_tenant_id_automatically(): void
    {
        $a = $this->makeTenant('Usaha A');
        $branch = app(TenantContext::class)->run($a, fn () => Branch::create(['code' => 'X1', 'name' => 'Cabang X']));

        $this->assertSame($a->id, $branch->tenant_id);
    }

    public function test_writing_into_another_tenant_is_rejected(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $branchB = $this->firstBranch($b);

        $this->expectException(TenantViolationException::class);
        app(TenantContext::class)->run($a, fn () => $branchB->update(['name' => 'Diambil alih']));
    }

    public function test_cannot_create_record_for_another_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');

        $this->expectException(TenantViolationException::class);
        app(TenantContext::class)->run($a, fn () => Branch::create(['tenant_id' => $b->id, 'code' => 'Z', 'name' => 'Z']));
    }

    public function test_livewire_cannot_edit_branch_of_another_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $branchB = $this->firstBranch($b);

        Livewire::actingAs($this->owner($a))
            ->test(BranchesPage::class)
            ->call('edit', $branchB->id)
            ->assertNotFound();

        $this->assertNotSame('Diambil alih', $branchB->fresh()->name);
    }

    public function test_branch_list_only_shows_own_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $this->addBranch($b, 'RHS', 'Cabang Rahasia B');

        Livewire::actingAs($this->owner($a))
            ->test(BranchesPage::class)
            ->assertDontSee('Cabang Rahasia B');
    }

    public function test_livewire_cannot_edit_user_of_another_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');

        Livewire::actingAs($this->owner($a))
            ->test(UsersPage::class)
            ->assertDontSee($this->owner($b)->email)
            ->call('edit', $this->owner($b)->id)
            ->assertNotFound();
    }

    public function test_cannot_assign_role_or_branch_from_another_tenant(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $roleB = Role::forTenant($b)->where('slug', 'kasir')->first();

        Livewire::actingAs($this->owner($a))
            ->test(UsersPage::class)
            ->call('create')
            ->set('form.name', 'Penyusup')
            ->set('form.email', 'penyusup@contoh.test')
            ->set('form.password', 'rahasia123')
            ->set('form.roles', [(string) $roleB->id])
            ->set('form.branches', [(string) $this->firstBranch($b)->id])
            ->call('save')
            ->assertHasErrors(['form.roles.0', 'form.branches.0']);
    }

    public function test_switching_to_branch_of_another_tenant_returns_404(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');

        $this->actingAs($this->owner($a))
            ->post(route('branch.switch'), ['branch_id' => $this->firstBranch($b)->id])
            ->assertNotFound();
    }

    public function test_logo_is_stored_in_tenant_folder(): void
    {
        Storage::fake('public');
        $a = $this->makeTenant('Usaha A');

        Livewire::actingAs($this->owner($a))
            ->test(CompanyProfilePage::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 200))
            ->call('saveLogo')
            ->assertHasNoErrors();

        $path = CompanyProfile::forTenant($a)->value('logo_path');
        $this->assertStringStartsWith('tenants/'.$a->uuid.'/branding/', $path);
        Storage::disk('public')->assertExists($path);
    }
}
