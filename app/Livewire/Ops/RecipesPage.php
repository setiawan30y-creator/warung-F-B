<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\RecipeCostService;
use App\Domain\Inventory\RecipeService;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Concerns\TenantPage;
use App\Models\Menu;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockBalance;
use App\Models\Unit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Resep & HPP')]
class RecipesPage extends Component
{
    use TenantPage;

    #[Url]
    public string $tab = 'menu';

    public string $search = '';

    public bool $showCreate = false;

    public array $sub = ['name' => '', 'code' => '', 'base_unit_id' => '', 'category' => 'Racikan'];

    public function mount(): void
    {
        $this->authorize('recipes.manage');
        $this->tab = in_array($this->tab, ['menu', 'sub'], true) ? $this->tab : 'menu';
    }

    public function openMenu(int $menuId, RecipeService $recipes): void
    {
        $this->authorize('recipes.manage');
        $recipe = $recipes->forMenu(Menu::findOrFail($menuId));
        $this->redirectRoute('ops.recipes.edit', ['recipe' => $recipe->id], navigate: true);
    }

    public function createSub(RecipeService $recipes): void
    {
        $this->authorize('recipes.manage');
        $tenantId = $this->tenant()->id;
        $this->validate([
            'sub.name' => 'required|string|max:120',
            'sub.code' => ['required', 'alpha_dash', 'max:30', Rule::unique('inventory_items', 'code')->where('tenant_id', $tenantId)],
            'sub.base_unit_id' => ['required', Rule::in(Unit::available()->base()->pluck('id')->all())],
            'sub.category' => 'nullable|string|max:60',
        ], [], ['sub.name' => 'nama racikan', 'sub.code' => 'kode', 'sub.base_unit_id' => 'satuan hasil']);

        try {
            $recipe = $recipes->createSubRecipe($this->sub['name'], $this->sub['code'], Unit::findOrFail($this->sub['base_unit_id']), $this->sub['category'] ?: null);
        } catch (InventoryException $e) {
            $this->addError('sub.name', $e->getMessage());

            return;
        }
        $this->redirectRoute('ops.recipes.edit', ['recipe' => $recipe->id], navigate: true);
    }

    public function render(RecipeCostService $costs)
    {
        $branch = app(TenantContext::class)->branch();
        $q = $this->search;

        if ($this->tab === 'menu') {
            $rows = Menu::with(['recipe.currentVersion.items', 'category', 'branchSettings'])
                ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))
                ->where('is_active', true)->orderBy('name')->get()
                ->map(function (Menu $m) use ($costs, $branch) {
                    $price = $m->forBranch($branch)['price'];
                    $hpp = $costs->menuCost($m, $branch);

                    return ['menu' => $m, 'price' => $price, 'hpp' => $hpp, 'version' => $m->recipe?->currentVersion,
                        'margin' => $hpp !== null && $price > 0 ? ($price - $hpp) / $price : null];
                });
        } else {
            $rows = Recipe::with(['outputItem.baseUnit', 'currentVersion.items'])->where('type', 'sub')
                ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))->orderBy('name')->get()
                ->map(function (Recipe $r) use ($costs, $branch) {
                    $usedBy = RecipeItem::where('inventory_item_id', $r->output_item_id)
                        ->whereIn('recipe_version_id', Recipe::whereNotNull('current_version_id')->pluck('current_version_id'))->count();

                    return ['recipe' => $r, 'version' => $r->currentVersion,
                        'cost' => $r->currentVersion ? $costs->breakdown($r->currentVersion, $branch)['per_yield'] : null,
                        'stock' => $branch ? (float) StockBalance::where('branch_id', $branch->id)->where('inventory_item_id', $r->output_item_id)->value('quantity') : 0,
                        'used_by' => $usedBy];
                });
        }

        return view('livewire.ops.recipes', [
            'rows' => $rows,
            'baseUnits' => Unit::available()->base()->orderBy('id')->get(),
            'branch' => $branch,
        ]);
    }
}
