<?php

namespace Tests\Feature;

use App\Domain\Tenancy\TenantContext;
use App\Livewire\Settings\BranchesPage;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/** Blueprint §23: perubahan penting tercatat dengan pelaku dan nilai sebelum/sesudah. */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_update_is_logged_with_before_and_after(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);
        $branch = $this->firstBranch($t);

        Livewire::actingAs($owner)->test(BranchesPage::class)
            ->call('edit', $branch->id)
            ->set('form.name', 'Cabang Baru')
            ->call('save')
            ->assertHasNoErrors();

        $log = AuditLog::forTenant($t)->where('event', 'branch.updated')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame('Cabang Utama', $log->old_values['name']);
        $this->assertSame('Cabang Baru', $log->new_values['name']);
    }

    public function test_password_is_never_written_to_audit_log(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);
        $owner->update(['password' => 'kataSandiBaru99']);

        $log = AuditLog::forTenant($t)->where('event', 'user.updated')->latest('id')->firstOrFail();
        $this->assertSame('••••••', $log->new_values['password']);
        $this->assertStringNotContainsString('kataSandiBaru99', json_encode($log->toArray()));
    }

    public function test_audit_logs_are_immutable(): void
    {
        $t = $this->makeTenant();
        $log = AuditLog::forTenant($t)->firstOrFail();

        try {
            $log->update(['event' => 'diubah']);
            $this->fail('Log audit seharusnya tidak bisa diubah.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_login_is_logged(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);

        $this->post(route('login.store'), ['email' => $owner->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $owner->id, 'tenant_id' => $t->id]);
    }

    public function test_branch_logs_record_active_branch(): void
    {
        $t = $this->makeTenant();
        $branch = $this->firstBranch($t);
        $ctx = app(TenantContext::class);
        $ctx->run($t, function () use ($ctx, $branch) {
            $ctx->setBranch($branch);
            Branch::create(['code' => 'B2', 'name' => 'Cabang Dua']);
        });

        $this->assertSame($branch->id, AuditLog::forTenant($t)->where('event', 'branch.created')->latest('id')->value('branch_id'));
    }
}
