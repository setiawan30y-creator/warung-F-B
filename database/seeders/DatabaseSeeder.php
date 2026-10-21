<?php

namespace Database\Seeders;

use App\Domain\SaaS\TenantProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Seeder produksi: hanya katalog izin. Data contoh ada di DemoSeeder. */
    public function run(): void
    {
        TenantProvisioner::syncPermissions();
        TenantProvisioner::syncDefaultRolePermissions();
    }
}
