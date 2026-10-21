<?php

namespace Tests\Feature;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\ProductionService;
use App\Domain\Inventory\PurchasingService;
use App\Domain\Inventory\RecipeCostService;
use App\Domain\Inventory\RecipeService;
use App\Domain\Inventory\SaleConsumption;
use App\Domain\Inventory\StockCountService;
use App\Domain\Inventory\StockService;
use App\Domain\Inventory\UnitConverter;
use App\Domain\Inventory\WasteService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Skenario usaha kentang crispy: kentang & minyak dibeli, bumbu tabur diracik sendiri (sub-resep),
 * lalu dipakai di menu. Blueprint §12–13 dan kriteria penerimaan §30 no. 6–7.
 */
class InventoryCoreTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant('Kentang Crispy');
        $this->branch = $this->firstBranch($this->tenant);
        app(TenantContext::class)->set($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);
        $this->actingAs($this->owner($this->tenant));
    }

    protected function unit(string $code): Unit
    {
        return Unit::whereNull('tenant_id')->where('code', $code)->firstOrFail();
    }

    protected function item(string $code, string $name, string $unit, float $lastCost = 0): InventoryItem
    {
        return InventoryItem::create(['code' => $code, 'name' => $name, 'type' => 'purchased',
            'base_unit_id' => $this->unit($unit)->id, 'last_cost' => $lastCost]);
    }

    protected function stock(): StockService
    {
        return app(StockService::class);
    }

    /** Bahan beli + bumbu tabur racikan + menu kentang crispy. */
    protected function scenario(): array
    {
        $kentang = $this->item('KTG', 'Kentang', 'g');
        $kentang->conversions()->create(['unit_id' => $this->unit('karung')->id, 'factor' => 25000]);
        $minyak = $this->item('MYK', 'Minyak goreng', 'ml');
        $garam = $this->item('GRM', 'Garam', 'g', 10);
        $cabai = $this->item('CBB', 'Bubuk cabai', 'g', 120);
        $gula = $this->item('GLA', 'Gula', 'g', 16);
        $cup = $this->item('CUP', 'Cup kertas', 'pcs', 500);

        $recipes = app(RecipeService::class);
        $bumbu = $recipes->createSubRecipe('Bumbu tabur balado', 'BTB', $this->unit('g'));
        // 100 g garam + 200 g cabai + 100 g gula → hasil 380 g (susut 20 g saat diayak)
        $recipes->saveVersion($bumbu, 380, [
            ['inventory_item_id' => $garam->id, 'quantity' => 100, 'unit_id' => $this->unit('g')->id],
            ['inventory_item_id' => $cabai->id, 'quantity' => 0.2, 'unit_id' => $this->unit('kg')->id],
            ['inventory_item_id' => $gula->id, 'quantity' => 1, 'unit_id' => $this->unit('ons')->id],
        ]);

        $menu = Menu::create(['code' => 'KC-R', 'name' => 'Kentang crispy reguler', 'price' => 15000]);
        $menuRecipe = $recipes->forMenu($menu);
        $recipes->saveVersion($menuRecipe, 1, [
            ['inventory_item_id' => $kentang->id, 'quantity' => 200, 'unit_id' => $this->unit('g')->id],
            ['inventory_item_id' => $minyak->id, 'quantity' => 30, 'unit_id' => $this->unit('ml')->id],
            ['inventory_item_id' => $bumbu->output_item_id, 'quantity' => 5, 'unit_id' => $this->unit('g')->id],
            ['inventory_item_id' => $cup->id, 'quantity' => 1, 'unit_id' => $this->unit('pcs')->id],
        ]);

        return compact('kentang', 'minyak', 'garam', 'cabai', 'gula', 'cup', 'bumbu', 'menu');
    }

    public function test_unit_conversion_uses_standard_and_packaging_factors(): void
    {
        $kentang = $this->item('KTG', 'Kentang', 'g');
        $kentang->conversions()->create(['unit_id' => $this->unit('karung')->id, 'factor' => 25000]);
        $conv = app(UnitConverter::class);

        $this->assertSame(1500.0, $conv->toBase($kentang->fresh(), 1.5, $this->unit('kg')));
        $this->assertSame(250.0, $conv->toBase($kentang->fresh(), 2.5, $this->unit('ons')));
        $this->assertSame(50000.0, $conv->toBase($kentang->fresh(), 2, $this->unit('karung')));

        $this->expectException(InventoryException::class);
        $conv->toBase($kentang->fresh(), 1, $this->unit('l'));
    }

    public function test_weighted_average_cost_on_receipts(): void
    {
        $kentang = $this->item('KTG', 'Kentang', 'g');
        $this->stock()->move($this->branch, $kentang, 1000, 'opening', 10);
        $this->stock()->move($this->branch, $kentang, 1000, 'purchase_receipt', 14);

        $b = $this->stock()->balance($this->branch, $kentang);
        $this->assertSame(2000.0, $b->quantity);
        $this->assertEqualsWithDelta(12.0, $b->avg_cost, 0.000001);

        $out = $this->stock()->move($this->branch, $kentang, -500, 'sale');
        $this->assertEqualsWithDelta(12.0, $out->unit_cost, 0.000001);
        $this->assertSame(-6000.0, $out->total_cost);
    }

    public function test_outgoing_stock_cannot_go_negative_by_default(): void
    {
        $kentang = $this->item('KTG', 'Kentang', 'g');
        $this->stock()->move($this->branch, $kentang, 100, 'opening', 12);

        $this->expectException(InventoryException::class);
        $this->expectExceptionMessage('tidak cukup');
        $this->stock()->move($this->branch, $kentang, -150, 'waste');
    }

    public function test_menu_cost_uses_sub_recipe_cost_without_double_counting(): void
    {
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 25000, 'opening', 12);
        $this->stock()->move($this->branch, $s['minyak'], 2000, 'opening', 15);

        // Bumbu belum diproduksi → HPP teoritis dari resepnya: (100×10 + 200×120 + 100×16) / 380
        $bumbuPerGram = (1000 + 24000 + 1600) / 380;
        $costs = app(RecipeCostService::class);
        $this->assertEqualsWithDelta($bumbuPerGram, $costs->unitCost($s['bumbu']->outputItem, $this->branch), 0.0001);

        $expected = 200 * 12 + 30 * 15 + 5 * $bumbuPerGram + 500;
        $this->assertEqualsWithDelta(round($expected, 2), $costs->menuCost($s['menu']->fresh(), $this->branch), 0.01);

        // Bahan mentah penyusun bumbu tidak muncul sebagai baris terpisah di HPP menu.
        $lines = collect($costs->breakdown($s['menu']->recipe->currentVersion, $this->branch)['lines']);
        $this->assertFalse($lines->contains(fn ($l) => $l['item']->id === $s['cabai']->id));
        $this->assertCount(4, $lines);
    }

    public function test_production_batch_moves_raw_to_produced_stock_at_actual_cost(): void
    {
        $s = $this->scenario();
        foreach (['garam' => 10, 'cabai' => 120, 'gula' => 16] as $k => $cost) {
            $this->stock()->move($this->branch, $s[$k], 5000, 'opening', $cost);
        }

        $prod = app(ProductionService::class);
        $batch = $prod->createDraft($s['bumbu'], 760, $this->branch); // 2× resep
        $this->assertSame(200.0, $batch->consumptions->firstWhere('inventory_item_id', $s['garam']->id)->planned_quantity);

        // Hasil aktual 740 g (selisih 2,6%) tanpa alasan → ditolak
        try {
            $prod->post($batch, 740);
            $this->fail('Selisih tanpa alasan seharusnya ditolak.');
        } catch (InventoryException $e) {
            $this->assertStringContainsString('alasan', $e->getMessage());
        }

        $batch = $prod->post($batch->fresh(), 740, [], 'Sebagian tercecer saat diayak');
        $total = 200 * 10 + 400 * 120 + 200 * 16;
        $this->assertSame((float) $total, $batch->total_cost);
        $this->assertEqualsWithDelta($total / 740, $batch->unit_cost, 0.000001);

        $this->assertSame(4800.0, $this->stock()->quantity($this->branch, $s['garam']));
        $this->assertSame(740.0, $this->stock()->quantity($this->branch, $s['bumbu']->outputItem));
        $this->assertEqualsWithDelta($total / 740, $this->stock()->balance($this->branch, $s['bumbu']->outputItem)->avg_cost, 0.000001);
    }

    public function test_selling_a_menu_reduces_sub_recipe_stock_not_raw_ingredients(): void
    {
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 25000, 'opening', 12);
        $this->stock()->move($this->branch, $s['minyak'], 2000, 'opening', 15);
        $this->stock()->move($this->branch, $s['bumbu']->outputItem, 380, 'opening', 70);
        $this->stock()->move($this->branch, $s['cup'], 100, 'opening', 500);
        $this->stock()->move($this->branch, $s['cabai'], 1000, 'opening', 120);

        $hpp = app(SaleConsumption::class)->consume($s['menu']->fresh(), 3, $this->branch);

        $this->assertSame(24400.0, $this->stock()->quantity($this->branch, $s['kentang']));
        $this->assertSame(365.0, $this->stock()->quantity($this->branch, $s['bumbu']->outputItem));
        $this->assertSame(1000.0, $this->stock()->quantity($this->branch, $s['cabai'])); // tidak tersentuh
        $this->assertSame(round(3 * (200 * 12 + 30 * 15 + 5 * 70 + 500), 2), $hpp);
    }

    public function test_historical_cost_snapshot_survives_price_and_recipe_changes(): void
    {
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 1000, 'opening', 12);
        $sold = $this->stock()->move($this->branch, $s['kentang'], -200, 'sale');
        $v1 = $s['menu']->recipe->current_version_id;

        $this->stock()->move($this->branch, $s['kentang'], 1000, 'purchase_receipt', 20);
        app(RecipeService::class)->saveVersion($s['menu']->recipe->fresh(), 1, [
            ['inventory_item_id' => $s['kentang']->id, 'quantity' => 250, 'unit_id' => $this->unit('g')->id],
        ]);

        $this->assertEqualsWithDelta(12.0, $sold->fresh()->unit_cost, 0.000001);
        $this->assertSame(4, RecipeVersion::find($v1)->items()->count());
        $this->assertNotSame($v1, $s['menu']->recipe->fresh()->current_version_id);

        $this->expectException(LogicException::class);
        $sold->update(['unit_cost' => 1]);
    }

    public function test_unchanged_recipe_does_not_create_new_version(): void
    {
        $s = $this->scenario();
        $recipe = $s['menu']->recipe->fresh();
        $before = $recipe->versions()->count();
        $lines = $recipe->currentVersion->items->map(fn ($i) => ['inventory_item_id' => $i->inventory_item_id, 'quantity' => $i->quantity, 'unit_id' => $i->unit_id])->all();

        app(RecipeService::class)->saveVersion($recipe, 1, $lines);

        $this->assertSame($before, $recipe->versions()->count());
    }

    public function test_sub_recipe_cannot_contain_itself_through_another_sub_recipe(): void
    {
        $s = $this->scenario();
        $recipes = app(RecipeService::class);
        $saus = $recipes->createSubRecipe('Saus balado', 'SBL', $this->unit('g'));
        $recipes->saveVersion($saus, 500, [
            ['inventory_item_id' => $s['bumbu']->output_item_id, 'quantity' => 50, 'unit_id' => $this->unit('g')->id],
        ]);

        $this->expectException(InventoryException::class);
        $recipes->saveVersion($s['bumbu']->fresh(), 380, [
            ['inventory_item_id' => $saus->output_item_id, 'quantity' => 10, 'unit_id' => $this->unit('g')->id],
        ]);
    }

    public function test_purchase_order_partial_receipt_updates_stock_and_status(): void
    {
        $s = $this->scenario();
        $supplier = Supplier::create(['name' => 'Pemasok Sayur']);
        $buy = app(PurchasingService::class);
        $po = $buy->savePurchaseOrder(null, $this->branch, $supplier, [
            ['inventory_item_id' => $s['kentang']->id, 'quantity' => 2, 'unit_id' => $this->unit('karung')->id, 'unit_price' => 300000],
        ], now()->toDateString(), null, null);
        $this->assertSame(600000.0, $po->total);
        $this->assertStringStartsWith('PO-PST-', $po->number);

        $buy->markOrdered($po);
        $line = $po->items->first();
        $buy->receive($po->fresh(), [$line->id => 1]);
        $this->assertSame('partial', $po->fresh()->status);
        $this->assertSame(25000.0, $this->stock()->quantity($this->branch, $s['kentang']));
        $this->assertEqualsWithDelta(12.0, $s['kentang']->fresh()->last_cost, 0.000001);

        try {
            $buy->receive($po->fresh(), [$line->id => 2]);
            $this->fail('Penerimaan melebihi pesanan seharusnya ditolak.');
        } catch (InventoryException) {
        }

        $buy->receive($po->fresh(), [$line->id => 1]);
        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(50000.0, $this->stock()->quantity($this->branch, $s['kentang']));
    }

    public function test_large_waste_needs_approval_from_another_user(): void
    {
        config(['inventory.waste_approval_threshold' => 50000]);
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 25000, 'opening', 12);
        $gudang = $this->member($this->tenant, 'gudang', [$this->branch]);
        app(TenantContext::class)->set($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        $waste = app(WasteService::class);
        $small = $waste->record($this->branch, $s['kentang'], 1, $this->unit('kg')->id, 'rusak', null, $gudang);
        $this->assertSame('approved', $small->status); // Rp12.000 di bawah batas
        $this->assertSame(24000.0, $this->stock()->quantity($this->branch, $s['kentang']));

        $big = $waste->record($this->branch, $s['kentang'], 5, $this->unit('kg')->id, 'basi', 'Kentang bertunas', $gudang);
        $this->assertSame('pending', $big->status); // Rp60.000 di atas batas
        $this->assertSame(24000.0, $this->stock()->quantity($this->branch, $s['kentang']));

        try {
            $waste->approve($big, $gudang);
            $this->fail('Gudang tidak berwenang menyetujui.');
        } catch (InventoryException) {
        }

        $waste->approve($big->fresh(), $this->owner($this->tenant));
        $this->assertSame(19000.0, $this->stock()->quantity($this->branch, $s['kentang']));
        $this->assertSame(2, StockMovement::where('type', 'waste')->count());
    }

    public function test_stock_count_posts_variance_against_current_balance(): void
    {
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 10000, 'opening', 12);
        $svc = app(StockCountService::class);
        $count = $svc->start($this->branch);
        $line = $count->lines->firstWhere('inventory_item_id', $s['kentang']->id);
        $this->assertSame(10000.0, $line->system_quantity);

        $this->stock()->move($this->branch, $s['kentang'], -1000, 'sale'); // terjual saat opname berlangsung
        $svc->saveCounts($count, [$line->id => 8500]);
        $count = $svc->post($count->fresh());

        $line = $line->fresh();
        $this->assertSame(9000.0, $line->posted_system_quantity);
        $this->assertSame(-500.0, $line->variance);
        $this->assertSame(-6000.0, $line->variance_value);
        $this->assertSame(8500.0, $this->stock()->quantity($this->branch, $s['kentang']));
        $this->assertSame('posted', $count->status);
    }

    public function test_balances_reconcile_with_ledger(): void
    {
        $s = $this->scenario();
        $this->stock()->move($this->branch, $s['kentang'], 1000, 'opening', 12);
        $this->stock()->move($this->branch, $s['kentang'], -300, 'sale');
        $this->stock()->move($this->branch, $s['minyak'], 500, 'opening', 15);
        $this->assertSame([], $this->stock()->reconcile());

        StockBalance::where('inventory_item_id', $s['kentang']->id)->update(['quantity' => 999]);
        $this->assertCount(1, $this->stock()->reconcile());
    }

    public function test_inventory_data_is_isolated_between_tenants(): void
    {
        $s = $this->scenario();
        $other = $this->makeTenant('Usaha Lain');
        $otherBranch = $this->firstBranch($other);

        app(TenantContext::class)->run($other, function () use ($s, $otherBranch) {
            $this->assertNull(InventoryItem::find($s['kentang']->id));
            $this->assertSame(0, Recipe::count());
            $this->expectException(InventoryException::class);
            $this->stock()->move($otherBranch, $s['kentang'], 10, 'opening', 1);
        });
    }
}
