<?php

namespace App\Domain\Inventory;

use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    public function __construct(protected UnitConverter $units) {}

    public function forMenu(Menu $menu): Recipe
    {
        return Recipe::firstOrCreate(['menu_id' => $menu->id], ['type' => 'menu', 'name' => $menu->name]);
    }

    /** Buat sub-resep beserta bahan hasil racikannya. */
    public function createSubRecipe(string $name, string $code, Unit $baseUnit, ?string $category = null): Recipe
    {
        return DB::transaction(function () use ($name, $code, $baseUnit, $category) {
            $item = InventoryItem::create([
                'code' => strtoupper($code), 'name' => $name, 'category' => $category ?? 'Racikan',
                'type' => 'produced', 'base_unit_id' => $baseUnit->id,
            ]);

            return Recipe::create(['type' => 'sub', 'name' => $name, 'output_item_id' => $item->id]);
        });
    }

    /**
     * Simpan komposisi sebagai versi baru. Versi lama tetap utuh untuk HPP historis.
     * Mengembalikan versi saat ini tanpa membuat versi baru bila tidak ada perubahan.
     *
     * @param  array<int, array{inventory_item_id:int, quantity:float, unit_id:int}>  $lines
     */
    public function saveVersion(Recipe $recipe, float $yield, array $lines, ?string $notes = null, ?string $effectiveFrom = null): RecipeVersion
    {
        if ($yield <= 0) {
            throw new InventoryException('Jumlah hasil harus lebih dari nol.');
        }
        if ($lines === []) {
            throw new InventoryException('Resep minimal berisi satu bahan.');
        }

        $prepared = [];
        $seen = [];
        foreach ($lines as $line) {
            $item = InventoryItem::with('baseUnit', 'conversions')->findOrFail($line['inventory_item_id']);
            if (isset($seen[$item->id])) {
                throw new InventoryException("{$item->name} tercantum lebih dari sekali. Gabungkan takarannya.");
            }
            $seen[$item->id] = true;
            if ((float) $line['quantity'] <= 0) {
                throw new InventoryException("Takaran {$item->name} harus lebih dari nol.");
            }
            $prepared[] = [
                'inventory_item_id' => $item->id,
                'quantity' => round((float) $line['quantity'], 4),
                'unit_id' => (int) $line['unit_id'],
                'base_quantity' => $this->units->toBase($item, (float) $line['quantity'], (int) $line['unit_id']),
            ];
        }

        if ($recipe->isSub()) {
            $this->guardCycle($recipe, array_keys($seen));
        }

        $current = $recipe->currentVersion?->load('items');
        if ($current && $this->sameComposition($current, $yield, $prepared)) {
            return $current;
        }

        return DB::transaction(function () use ($recipe, $yield, $prepared, $notes, $effectiveFrom) {
            $version = RecipeVersion::create([
                'recipe_id' => $recipe->id,
                'version' => (int) $recipe->versions()->max('version') + 1,
                'yield_quantity' => round($yield, 4),
                'effective_from' => $effectiveFrom ?? now()->toDateString(),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);
            $version->items()->createMany($prepared);
            $recipe->update(['current_version_id' => $version->id]);
            $recipe->setRelation('currentVersion', $version->load('items'));

            return $version;
        });
    }

    /** Sub-resep tidak boleh memakai hasilnya sendiri, langsung maupun lewat sub-resep lain. */
    protected function guardCycle(Recipe $recipe, array $ingredientIds): void
    {
        $target = $recipe->output_item_id;
        $stack = $ingredientIds;
        $visited = [];
        while ($stack) {
            $id = array_pop($stack);
            if ($id === $target) {
                throw new InventoryException('Resep ini memakai hasil racikannya sendiri, langsung atau lewat racikan lain.');
            }
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            $version = Recipe::where('output_item_id', $id)->first()?->currentVersion;
            if ($version) {
                array_push($stack, ...$version->items()->pluck('inventory_item_id')->all());
            }
        }
    }

    protected function sameComposition(RecipeVersion $v, float $yield, array $lines): bool
    {
        if (abs($v->yield_quantity - $yield) > 0.00005 || $v->items->count() !== count($lines)) {
            return false;
        }
        $existing = $v->items->mapWithKeys(fn ($i) => [$i->inventory_item_id => [round($i->quantity, 4), $i->unit_id]])->all();
        foreach ($lines as $l) {
            if (($existing[$l['inventory_item_id']] ?? null) !== [round($l['quantity'], 4), $l['unit_id']]) {
                return false;
            }
        }

        return true;
    }
}
