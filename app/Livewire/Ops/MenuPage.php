<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\RecipeCostService;
use App\Domain\Inventory\RecipeService;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Concerns\TenantPage;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\PriceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Menu & harga')]
class MenuPage extends Component
{
    use TenantPage, WithFileUploads;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    public bool $showForm = false;

    public bool $showCategories = false;

    public ?int $editingId = null;

    public array $form = [];

    public array $branchForm = [];

    public $photo;

    public string $newCategory = '';

    public ?int $historyId = null;

    public function mount(): void
    {
        $this->authorize('menu.manage');
    }

    protected function resetForm(): void
    {
        $this->form = ['code' => '', 'name' => '', 'menu_category_id' => '', 'description' => '', 'price' => '', 'is_active' => true];
        $this->branchForm = Branch::orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => ['price' => '', 'is_available' => true, 'is_sold_out' => false]])->all();
        $this->photo = null;
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->resetForm();
        $this->form['menu_category_id'] = $this->category;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $menu = Menu::with('branchSettings')->findOrFail($id);
        $this->editingId = $menu->id;
        $this->resetForm();
        $this->form = [
            'code' => $menu->code, 'name' => $menu->name, 'menu_category_id' => (string) ($menu->menu_category_id ?? ''),
            'description' => (string) $menu->description, 'price' => (string) $menu->price, 'is_active' => $menu->is_active,
        ];
        foreach ($menu->branchSettings as $s) {
            if (isset($this->branchForm[$s->branch_id])) {
                $this->branchForm[$s->branch_id] = ['price' => $s->price === null ? '' : (string) $s->price, 'is_available' => $s->is_available, 'is_sold_out' => $s->is_sold_out];
            }
        }
        $this->showForm = true;
    }

    protected function rules(): array
    {
        $tenantId = $this->tenant()->id;

        return [
            'form.code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('menus', 'code')->where('tenant_id', $tenantId)->ignore($this->editingId)],
            'form.name' => 'required|string|max:120',
            'form.menu_category_id' => ['nullable', Rule::exists('menu_categories', 'id')->where('tenant_id', $tenantId)],
            'form.description' => 'nullable|string|max:500',
            'form.price' => 'required|numeric|min:0|max:100000000',
            'form.is_active' => 'boolean',
            'branchForm.*.price' => 'nullable|numeric|min:0|max:100000000',
            'branchForm.*.is_available' => 'boolean',
            'branchForm.*.is_sold_out' => 'boolean',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.code' => 'kode menu', 'form.name' => 'nama menu', 'form.price' => 'harga', 'branchForm.*.price' => 'harga cabang', 'photo' => 'foto'];
    }

    public function save(): void
    {
        $this->authorize('menu.manage');
        $this->validate();
        $f = $this->form;

        DB::transaction(function () use ($f) {
            $menu = $this->editingId ? Menu::findOrFail($this->editingId) : new Menu;
            $oldPrice = $menu->exists ? (float) $menu->price : null;
            $menu->fill([
                'code' => strtoupper($f['code']), 'name' => $f['name'], 'menu_category_id' => $f['menu_category_id'] ?: null,
                'description' => $f['description'] ?: null, 'price' => round((float) $f['price'], 2), 'is_active' => (bool) $f['is_active'],
            ]);
            if ($this->photo) {
                $old = $menu->photo_path;
                $menu->photo_path = $this->photo->storeAs('tenants/'.$this->tenant()->uuid.'/menu',
                    Str::random(16).'.'.$this->photo->getClientOriginalExtension(), 'public');
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }
            $menu->save();
            if ($oldPrice !== (float) $menu->price) {
                PriceHistory::create(['menu_id' => $menu->id, 'branch_id' => null, 'old_price' => $oldPrice, 'new_price' => $menu->price, 'user_id' => auth()->id()]);
            }

            $validBranches = Branch::pluck('id')->all();
            foreach ($this->branchForm as $branchId => $b) {
                if (! in_array((int) $branchId, $validBranches, true)) {
                    continue;
                }
                $price = $b['price'] === '' || $b['price'] === null ? null : round((float) $b['price'], 2);
                $setting = BranchMenu::firstOrNew(['branch_id' => (int) $branchId, 'menu_id' => $menu->id]);
                $oldBranchPrice = $setting->exists ? $setting->price : null;
                $isDefault = $price === null && $b['is_available'] && ! $b['is_sold_out'];
                if (! $setting->exists && $isDefault) {
                    continue;
                }
                $setting->fill(['price' => $price, 'is_available' => (bool) $b['is_available'], 'is_sold_out' => (bool) $b['is_sold_out']])->save();
                if ($oldBranchPrice !== $price && ($oldBranchPrice !== null || $price !== null)) {
                    PriceHistory::create(['menu_id' => $menu->id, 'branch_id' => (int) $branchId, 'old_price' => $oldBranchPrice, 'new_price' => $price, 'user_id' => auth()->id()]);
                }
            }
        });

        $this->showForm = false;
        $this->dispatch('toast', message: $this->editingId ? 'Menu diperbarui.' : 'Menu ditambahkan.');
    }

    public function toggleSoldOut(int $id): void
    {
        $this->authorize('menu.manage');
        $menu = Menu::findOrFail($id);
        $branch = $this->branch();
        $s = BranchMenu::firstOrNew(['branch_id' => $branch->id, 'menu_id' => $menu->id], ['is_available' => true]);
        $s->is_sold_out = ! $s->is_sold_out;
        $s->save();
        $this->dispatch('toast', message: $s->is_sold_out ? "{$menu->name} ditandai habis di {$branch->name}." : "{$menu->name} tersedia lagi di {$branch->name}.");
    }

    public function openRecipe(int $id): void
    {
        $this->authorize('recipes.manage');
        abort_unless($this->hasModule('inventory'), 403);
        $recipe = app(RecipeService::class)->forMenu(Menu::findOrFail($id));
        $this->redirectRoute('ops.recipes.edit', ['recipe' => $recipe->id], navigate: true);
    }

    public function addCategory(): void
    {
        $this->authorize('menu.manage');
        $this->validate(['newCategory' => ['required', 'string', 'max:80', Rule::unique('menu_categories', 'name')->where('tenant_id', $this->tenant()->id)]],
            [], ['newCategory' => 'nama kategori']);
        MenuCategory::create(['name' => trim($this->newCategory), 'sort_order' => (int) MenuCategory::max('sort_order') + 1]);
        $this->newCategory = '';
    }

    public function moveCategory(int $id, int $dir): void
    {
        $this->authorize('menu.manage');
        $cats = MenuCategory::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $cats->search(fn ($c) => $c->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $cats->count()) {
            return;
        }
        [$a, $b] = [$cats[$i], $cats[$j]];
        $cats[$i] = $b;
        $cats[$j] = $a;
        foreach ($cats as $pos => $c) {
            $c->update(['sort_order' => $pos]);
        }
    }

    public function deleteCategory(int $id): void
    {
        $this->authorize('menu.manage');
        $cat = MenuCategory::withCount('menus')->findOrFail($id);
        if ($cat->menus_count > 0) {
            $this->dispatch('toast', message: "Kategori {$cat->name} masih berisi {$cat->menus_count} menu.");

            return;
        }
        $cat->delete();
    }

    public function render(RecipeCostService $costs)
    {
        $branch = app(TenantContext::class)->branch();
        $menus = Menu::with(['category', 'branchSettings', 'recipe.currentVersion'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->category !== '', fn ($q) => $this->category === 'none' ? $q->whereNull('menu_category_id') : $q->where('menu_category_id', (int) $this->category))
            ->orderBy('name')->get();
        $hasInventory = $this->hasModule('inventory');

        $rows = $menus->map(function (Menu $m) use ($branch, $costs, $hasInventory) {
            $b = $m->forBranch($branch);
            $hpp = $hasInventory ? $costs->menuCost($m, $branch) : null;

            return ['menu' => $m, 'branch' => $b, 'hpp' => $hpp,
                'margin' => ($hpp !== null && $b['price'] > 0) ? ($b['price'] - $hpp) / $b['price'] : null];
        });

        return view('livewire.ops.menu', [
            'rows' => $rows,
            'categories' => MenuCategory::withCount('menus')->orderBy('sort_order')->orderBy('id')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'activeBranch' => $branch,
            'hasInventory' => $hasInventory,
            'canRecipes' => $this->user()->can('recipes.manage'),
            'history' => $this->historyId ? PriceHistory::with(['user:id,name', 'branch:id,name'])->where('menu_id', $this->historyId)->latest('id')->limit(30)->get() : null,
            'historyMenu' => $this->historyId ? Menu::find($this->historyId) : null,
        ]);
    }
}
