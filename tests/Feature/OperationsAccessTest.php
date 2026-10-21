<?php

namespace Tests\Feature;

use App\Domain\Inventory\RecipeService;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Ops\InventoryPage;
use App\Livewire\Ops\MenuPage;
use App\Livewire\Ops\RecipeEditor;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\StockBalance;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Akses halaman Fase 2: izin per peran, modul per paket, dan isolasi tenant di halaman. */
class OperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected array $pages = ['ops.menu', 'ops.recipes', 'ops.production', 'ops.inventory', 'ops.ledger', 'ops.waste', 'ops.counts', 'ops.purchasing'];

    public function test_owner_can_open_every_operations_page(): void
    {
        $t = $this->makeTenant();
        foreach ($this->pages as $route) {
            $this->actingAs($this->owner($t))->get(route($route))->assertOk();
        }
    }

    public function test_pages_follow_role_permissions(): void
    {
        $t = $this->makeTenant();
        $branch = $this->firstBranch($t);
        $kasir = $this->member($t, 'kasir', [$branch]);
        $gudang = $this->member($t, 'gudang', [$branch]);

        foreach ($this->pages as $route) {
            $this->actingAs($kasir)->get(route($route))->assertForbidden();
        }
        foreach (['ops.inventory', 'ops.ledger', 'ops.waste', 'ops.counts', 'ops.purchasing'] as $route) {
            $this->actingAs($gudang)->get(route($route))->assertOk();
        }
        foreach (['ops.menu', 'ops.recipes', 'ops.production'] as $route) {
            $this->actingAs($gudang)->get(route($route))->assertForbidden();
        }
    }

    public function test_starter_plan_only_gets_menu_module(): void
    {
        $t = $this->makeTenant('Usaha Kecil', 'starter');
        $this->actingAs($this->owner($t))->get(route('ops.menu'))->assertOk()->assertDontSee('<th class="num">HPP</th>', false)->assertDontSee('Resep &amp; HPP', false);
        foreach (['ops.recipes', 'ops.inventory', 'ops.purchasing'] as $route) {
            $this->actingAs($this->owner($t))->get(route($route))->assertForbidden();
        }
    }

    public function test_recipe_editor_of_another_tenant_is_not_found(): void
    {
        $a = $this->makeTenant('Usaha A');
        $b = $this->makeTenant('Usaha B');
        $recipeB = app(TenantContext::class)->run($b, fn () => app(RecipeService::class)
            ->forMenu(Menu::create(['code' => 'X', 'name' => 'Menu rahasia', 'price' => 1000])));

        $this->actingAs($this->owner($a))->get(route('ops.recipes.edit', $recipeB->id))->assertNotFound();
    }

    public function test_menu_price_change_is_recorded_in_history(): void
    {
        $t = $this->makeTenant();
        $owner = $this->owner($t);

        Livewire::actingAs($owner)->test(MenuPage::class)
            ->call('create')
            ->set('form.code', 'es-teh')->set('form.name', 'Es teh')->set('form.price', 5000)
            ->call('save')->assertHasNoErrors();

        $menu = Menu::forTenant($t)->where('code', 'ES-TEH')->firstOrFail();
        Livewire::actingAs($owner)->test(MenuPage::class)
            ->call('edit', $menu->id)->set('form.price', 6000)->call('save')->assertHasNoErrors();

        $this->assertSame([null, 5000.0], [$menu->priceHistories()->orderBy('id')->first()->old_price, $menu->priceHistories()->orderBy('id')->first()->new_price]);
        $this->assertSame(6000.0, $menu->priceHistories()->latest('id')->first()->new_price);
    }

    public function test_opening_balance_then_adjustment_requires_approval_permission(): void
    {
        $t = $this->makeTenant();
        $branch = $this->firstBranch($t);
        $gudang = $this->member($t, 'gudang', [$branch]);
        $item = app(TenantContext::class)->run($t, fn () => InventoryItem::create(['code' => 'KTG', 'name' => 'Kentang', 'type' => 'purchased',
            'base_unit_id' => Unit::whereNull('tenant_id')->where('code', 'g')->value('id')]));

        Livewire::actingAs($gudang)->test(InventoryPage::class)
            ->call('openAdjust', $item->id)
            ->set('adjust.quantity', 2)->set('adjust.unit', (string) Unit::whereNull('tenant_id')->where('code', 'kg')->value('id'))
            ->set('adjust.unit_cost', 12000)
            ->call('saveAdjust')->assertHasNoErrors();

        $this->assertSame(2000.0, (float) StockBalance::forTenant($t)->where('inventory_item_id', $item->id)->value('quantity'));
        $this->assertEqualsWithDelta(12.0, (float) StockBalance::forTenant($t)->where('inventory_item_id', $item->id)->value('avg_cost'), 0.0001);

        Livewire::actingAs($gudang)->test(InventoryPage::class)->call('openAdjust', $item->id)->assertForbidden();
    }

    public function test_recipe_editor_saves_new_version(): void
    {
        $t = $this->makeTenant();
        [$recipe, $item] = app(TenantContext::class)->run($t, function () {
            $item = InventoryItem::create(['code' => 'KTG', 'name' => 'Kentang', 'type' => 'purchased', 'last_cost' => 12,
                'base_unit_id' => Unit::whereNull('tenant_id')->where('code', 'g')->value('id')]);

            return [app(RecipeService::class)->forMenu(Menu::create(['code' => 'KC', 'name' => 'Kentang crispy', 'price' => 15000])), $item];
        });

        Livewire::actingAs($this->owner($t))->test(RecipeEditor::class, ['recipe' => $recipe->id])
            ->set('lines.0.item', (string) $item->id)
            ->set('lines.0.qty', '150')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Rp1.800');

        $this->assertSame(1, $recipe->fresh()->currentVersion->version);
    }
}
