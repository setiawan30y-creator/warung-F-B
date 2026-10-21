<?php

namespace Database\Seeders;

use App\Domain\Inventory\ProductionService;
use App\Domain\Inventory\PurchasingService;
use App\Domain\Inventory\RecipeService;
use App\Domain\Inventory\StockService;
use App\Domain\SaaS\TenantProvisioner;
use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
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

            $gudang = User::create(['tenant_id' => $timur->tenant_id, 'name' => 'Staf Gudang',
                'email' => 'gudang@contoh.test', 'password' => 'password123']);
            $gudang->roles()->attach(Role::where('slug', 'gudang')->value('id'));
            $gudang->branches()->attach(Branch::where('code', 'PST')->value('id'), ['tenant_id' => $timur->tenant_id]);

            $this->catalog(Branch::where('code', 'PST')->first());
        });

        $context->forget();
        $provisioner->provision([
            'name' => 'Kedai Kopi Contoh', 'business_type' => 'Kedai kopi', 'plan' => 'starter',
            'owner_name' => 'Pemilik Kedai', 'owner_email' => 'kedai@contoh.test', 'owner_password' => 'password123',
        ]);
    }

    /** Bahan, racikan, menu, dan transaksi contoh untuk usaha kentang crispy. */
    protected function catalog(Branch $branch): void
    {
        $context = app(TenantContext::class);
        $context->setBranch($branch);
        $u = fn (string $code) => Unit::whereNull('tenant_id')->where('code', $code)->value('id');
        $stock = app(StockService::class);
        $recipes = app(RecipeService::class);

        $items = [];
        foreach ([
            ['KTG', 'Kentang', 'Sayur', 'g', 12, 5000, ['karung' => 25000]],
            ['MYK', 'Minyak goreng', 'Minyak', 'ml', 18, 3000, ['jeriken' => 18000]],
            ['TPG', 'Tepung bumbu', 'Kering', 'g', 22, 1000, ['pack' => 1000]],
            ['GRM', 'Garam', 'Bumbu', 'g', 8, 500, []],
            ['CBB', 'Bubuk cabai', 'Bumbu', 'g', 110, 300, []],
            ['GLA', 'Gula pasir', 'Bumbu', 'g', 16, 500, []],
            ['KJU', 'Keju cheddar', 'Susu', 'g', 95, 1000, ['box' => 2000]],
            ['SSU', 'Susu cair', 'Susu', 'ml', 20, 1000, ['l' => 1000]],
            ['MTG', 'Mentega', 'Susu', 'g', 60, 500, []],
            ['CUP', 'Cup kertas 12 oz', 'Kemasan', 'pcs', 650, 100, ['pack' => 50]],
        ] as [$code, $name, $cat, $unit, $cost, $min, $conv]) {
            $item = InventoryItem::create(['code' => $code, 'name' => $name, 'category' => $cat, 'type' => 'purchased',
                'base_unit_id' => $u($unit), 'min_stock' => $min, 'last_cost' => $cost]);
            foreach ($conv as $cu => $f) {
                $item->conversions()->create(['unit_id' => $u($cu), 'factor' => $f]);
            }
            $items[$code] = $item->load('baseUnit', 'conversions');
        }

        foreach (['KTG' => 30000, 'MYK' => 16000, 'TPG' => 4000, 'GRM' => 2000, 'CBB' => 900, 'GLA' => 1500,
            'KJU' => 2500, 'SSU' => 3000, 'MTG' => 800, 'CUP' => 80] as $code => $q) {
            $stock->move($branch, $items[$code], $q, 'opening', $items[$code]->last_cost, reason: 'Saldo awal');
        }

        $bumbu = $recipes->createSubRecipe('Bumbu tabur balado', 'BTB', Unit::find($u('g')), 'Racikan');
        $recipes->saveVersion($bumbu, 380, [
            ['inventory_item_id' => $items['GRM']->id, 'quantity' => 100, 'unit_id' => $u('g')],
            ['inventory_item_id' => $items['CBB']->id, 'quantity' => 200, 'unit_id' => $u('g')],
            ['inventory_item_id' => $items['GLA']->id, 'quantity' => 100, 'unit_id' => $u('g')],
        ], 'Resep awal');
        $saus = $recipes->createSubRecipe('Saus keju', 'SKJ', Unit::find($u('ml')), 'Racikan');
        $recipes->saveVersion($saus, 1100, [
            ['inventory_item_id' => $items['KJU']->id, 'quantity' => 500, 'unit_id' => $u('g')],
            ['inventory_item_id' => $items['SSU']->id, 'quantity' => 600, 'unit_id' => $u('ml')],
            ['inventory_item_id' => $items['MTG']->id, 'quantity' => 50, 'unit_id' => $u('g')],
        ], 'Resep awal');

        $prod = app(ProductionService::class);
        $prod->post($prod->createDraft($bumbu, 760, $branch), 750, [], 'Sedikit tercecer saat diayak');
        $prod->post($prod->createDraft($saus, 1100, $branch), 1100);

        $cat = MenuCategory::create(['name' => 'Kentang crispy', 'sort_order' => 0]);
        MenuCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        foreach ([
            ['KC-R', 'Kentang crispy reguler', 15000, 150, 20, 4, null],
            ['KC-L', 'Kentang crispy large', 22000, 230, 30, 6, null],
            ['KC-KJ', 'Kentang crispy saus keju', 20000, 150, 20, 2, 40],
        ] as [$code, $name, $price, $kentang, $minyak, $bumbuG, $sausMl]) {
            $menu = Menu::create(['code' => $code, 'name' => $name, 'price' => $price, 'menu_category_id' => $cat->id]);
            $lines = [
                ['inventory_item_id' => $items['KTG']->id, 'quantity' => $kentang, 'unit_id' => $u('g')],
                ['inventory_item_id' => $items['MYK']->id, 'quantity' => $minyak, 'unit_id' => $u('ml')],
                ['inventory_item_id' => $items['TPG']->id, 'quantity' => 15, 'unit_id' => $u('g')],
                ['inventory_item_id' => $bumbu->output_item_id, 'quantity' => $bumbuG, 'unit_id' => $u('g')],
                ['inventory_item_id' => $items['CUP']->id, 'quantity' => 1, 'unit_id' => $u('pcs')],
            ];
            if ($sausMl) {
                $lines[] = ['inventory_item_id' => $saus->output_item_id, 'quantity' => $sausMl, 'unit_id' => $u('ml')];
            }
            $recipes->saveVersion($recipes->forMenu($menu), 1, $lines);
        }

        $supplier = Supplier::create(['name' => 'Pemasok Sayur Segar', 'contact_name' => 'Bagian penjualan', 'phone' => '0812 0000 0000', 'lead_time_days' => 1]);
        Supplier::create(['name' => 'Toko Kemasan', 'lead_time_days' => 3]);
        $buy = app(PurchasingService::class);
        $po = $buy->savePurchaseOrder(null, $branch, $supplier, [
            ['inventory_item_id' => $items['KTG']->id, 'quantity' => 2, 'unit_id' => $u('karung'), 'unit_price' => 325000],
        ], now()->toDateString(), now()->addDay()->toDateString(), null);
        $buy->markOrdered($po);
    }
}
