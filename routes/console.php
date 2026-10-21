<?php

use App\Domain\Inventory\StockService;
use App\Domain\SaaS\TenantProvisioner;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('almara:sync-permissions', function () {
    TenantProvisioner::syncPermissions();
    $added = TenantProvisioner::syncDefaultRolePermissions();
    $this->info("Katalog izin diselaraskan. {$added} izin baru ditambahkan ke peran bawaan.");
})->purpose('Selaraskan tabel permissions dengan katalog di kode');

Artisan::command('almara:platform-admin {--email=} {--name=}', function () {
    $email = $this->option('email') ?: env('PLATFORM_ADMIN_EMAIL') ?: text('Email admin platform', required: true);
    $name = $this->option('name') ?: text('Nama', default: 'Admin Platform', required: true);
    $pass = password('Kata sandi (minimal 12 karakter)', required: true,
        validate: fn ($v) => strlen($v) < 12 ? 'Minimal 12 karakter.' : null);

    User::updateOrCreate(['email' => strtolower($email)], [
        'name' => $name, 'password' => $pass, 'is_platform_admin' => true, 'tenant_id' => null, 'is_active' => true,
    ]);
    $this->info("Admin platform {$email} siap. Masuk lewat /login.");
})->purpose('Buat atau perbarui akun admin platform');

Artisan::command('almara:stock-reconcile', function () {
    $total = 0;
    foreach (Tenant::all() as $tenant) {
        app(TenantContext::class)->run($tenant, function () use ($tenant, &$total) {
            foreach (app(StockService::class)->reconcile() as $i) {
                $total++;
                $this->warn("{$tenant->name}: cabang #{$i['branch_id']} bahan #{$i['item_id']} saldo {$i['balance']} ≠ ledger {$i['ledger']}");
            }
        });
    }
    $total ? $this->error("{$total} selisih ditemukan.") : $this->info('Semua saldo stok cocok dengan ledger.');
})->purpose('Cocokkan saldo stok dengan ledger pergerakan');
