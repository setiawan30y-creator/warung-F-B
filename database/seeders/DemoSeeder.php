<?php

namespace Database\Seeders;

use App\Domain\SaaS\TenantProvisioner;
use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba aplikasi secara lokal. Jangan dijalankan di produksi.
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(TenantProvisioner $provisioner, TenantContext $context): void
    {
        User::updateOrCreate(['email' => 'admin@platform.test'], [
            'name' => 'Admin Platform',
            'password' => 'password123',
            'is_platform_admin' => true,
        ]);

        $tenant = $provisioner->provision([
            'name' => 'Warung Contoh',
            'business_type' => 'Warung makan',
            'plan' => 'growth',
            'trial' => false,
            'owner_name' => 'Pemilik Usaha',
            'owner_email' => 'pemilik@contoh.test',
            'owner_password' => 'password123',
            'branch_name' => 'Cabang Pusat',
            'branch_code' => 'PST',
        ]);

        $context->run($tenant, function () {
            Branch::where('code', 'PST')->update(['address' => 'Jl. Contoh No. 1', 'city' => 'Jakarta', 'latitude' => -6.2000000, 'longitude' => 106.8166667]);
            $timur = Branch::create(['code' => 'TMR', 'name' => 'Cabang Timur', 'city' => 'Bekasi',
                'latitude' => -6.2383, 'longitude' => 106.9756, 'opening_hours' => Branch::defaultOpeningHours()]);

            $kasir = User::create(['tenant_id' => $timur->tenant_id, 'name' => 'Kasir Cabang Timur',
                'email' => 'kasir@contoh.test', 'password' => 'password123']);
            $kasir->roles()->attach(Role::where('slug', 'kasir')->value('id'));
            $kasir->branches()->attach($timur->id, ['tenant_id' => $timur->tenant_id]);

            $manajer = User::create(['tenant_id' => $timur->tenant_id, 'name' => 'Manajer Operasional',
                'email' => 'manajer@contoh.test', 'password' => 'password123', 'all_branches' => true]);
            $manajer->roles()->attach(Role::where('slug', 'manajer')->value('id'));
        });

        $provisioner->provision([
            'name' => 'Kedai Kopi Contoh', 'business_type' => 'Kedai kopi', 'plan' => 'starter',
            'owner_name' => 'Pemilik Kedai', 'owner_email' => 'kedai@contoh.test', 'owner_password' => 'password123',
        ]);
    }
}
