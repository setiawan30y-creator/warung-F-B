<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\RecipeCostService;
use App\Domain\Inventory\RecipeService;
use App\Domain\Inventory\UnitConverter;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\Recipe;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecipeEditor extends Component
{
    use TenantPage;

    #[Locked]
    public int $recipeId;

    public string $yield = '1';

    public string $notes = '';

    public string $effectiveFrom = '';

    public array $lines = [];

    public string $targetMargin = '65';

    public function mount($recipe): void
    {
        $this->authorize('recipes.manage');
        $r = Recipe::with('currentVersion.items')->findOrFail((int) $recipe); // global scope tenant → 404 untuk tenant lain
        $this->recipeId = $r->id;
        $v = $r->currentVersion;
        $this->yield = $v ? (string) (float) $v->yield_quantity : ($r->isSub() ? '' : '1');
        $this->effectiveFrom = now()->toDateString();
        $this->lines = $v
            ? $v->items->map(fn ($i) => ['item' => (string) $i->inventory_item_id, 'qty' => (string) (float) $i->quantity, 'unit' => (string) $i->unit_id])->all()
            : [['item' => '', 'qty' => '', 'unit' => '']];
    }

    protected function recipe(): Recipe
    {
        return Recipe::with(['menu.branchSettings', 'outputItem.baseUnit', 'currentVersion'])->findOrFail($this->recipeId);
    }

    public function addLine(): void
    {
        $this->lines[] = ['item' => '', 'qty' => '', 'unit' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines) ?: [['item' => '', 'qty' => '', 'unit' => '']];
    }

    public function updatedLines($value, $key): void
    {
        [$i, $field] = array_pad(explode('.', (string) $key), 2, null);
        if ($field === 'item' && $value) {
            $item = InventoryItem::find((int) $value);
            $this->lines[(int) $i]['unit'] = (string) ($item?->base_unit_id ?? '');
        }
    }

    public function save(RecipeService $recipes): void
    {
        $this->authorize('recipes.manage');
        $this->validate([
            'yield' => 'required|numeric|gt:0|max:100000000',
            'notes' => 'nullable|string|max:255',
            'effectiveFrom' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.item' => 'required|integer',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.unit' => 'required|integer',
        ], [], ['yield' => 'jumlah hasil', 'lines.*.item' => 'bahan', 'lines.*.qty' => 'takaran', 'lines.*.unit' => 'satuan', 'effectiveFrom' => 'tanggal berlaku']);

        $recipe = $this->recipe();
        $before = $recipe->current_version_id;
        try {
            $version = $recipes->saveVersion($recipe, (float) $this->yield,
                array_map(fn ($l) => ['inventory_item_id' => (int) $l['item'], 'quantity' => (float) $l['qty'], 'unit_id' => (int) $l['unit']], $this->lines),
                $this->notes ?: null, $this->effectiveFrom);
        } catch (InventoryException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }
        $this->notes = '';
        $this->dispatch('toast', message: $version->id === $before ? 'Tidak ada perubahan pada resep.' : "Resep disimpan sebagai versi {$version->version}.");
    }

    public function render(RecipeCostService $costs, UnitConverter $units)
    {
        $recipe = $this->recipe();
        $branch = app(TenantContext::class)->branch();
        $items = InventoryItem::with(['baseUnit', 'conversions'])->where('is_active', true)
            ->when($recipe->isSub(), fn ($q) => $q->where('id', '!=', $recipe->output_item_id))
            ->orderBy('type')->orderBy('name')->get();
        $byId = $items->keyBy('id');

        $preview = [];
        $total = 0.0;
        foreach ($this->lines as $i => $l) {
            $item = $byId[(int) ($l['item'] ?? 0)] ?? null;
            $row = ['cost' => null, 'unit_cost' => null, 'error' => null, 'source' => null, 'base' => null];
            if ($item && is_numeric($l['qty'] ?? null) && ($l['unit'] ?? '') !== '') {
                try {
                    $base = $units->toBase($item, (float) $l['qty'], (int) $l['unit']);
                    $uc = $costs->unitCost($item, $branch);
                    $row = ['base' => $base, 'unit_cost' => $uc, 'cost' => $base * $uc, 'error' => null, 'source' => $costs->costSource($item, $branch)];
                    $total += $base * $uc;
                } catch (InventoryException $e) {
                    $row['error'] = $e->getMessage();
                }
            }
            $preview[$i] = $row;
        }
        $yield = is_numeric($this->yield) && (float) $this->yield > 0 ? (float) $this->yield : null;
        $perYield = $yield ? $total / $yield : null;

        $price = $recipe->menu ? $recipe->menu->forBranch($branch)['price'] : null;
        $tm = is_numeric($this->targetMargin) ? min(max((float) $this->targetMargin, 0), 95) / 100 : null;

        return view('livewire.ops.recipe-editor', [
            'recipe' => $recipe,
            'items' => $items,
            'unitMap' => $units->optionsMap($items),
            'preview' => $preview,
            'total' => $total,
            'perYield' => $perYield,
            'price' => $price,
            'margin' => $price && $perYield !== null ? ($price - $perYield) / $price : null,
            'suggested' => $perYield !== null && $tm !== null && $tm < 1 ? ceil(($perYield / (1 - $tm)) / 500) * 500 : null,
            'versions' => $recipe->versions()->with('author:id,name')->withCount('items')->limit(10)->get(),
            'branch' => $branch,
            'outputUnit' => $recipe->outputItem?->unitCode(),
        ])->title(($recipe->isSub() ? 'Racikan ' : 'Resep ').$recipe->name);
    }
}
