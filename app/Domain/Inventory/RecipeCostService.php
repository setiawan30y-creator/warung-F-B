<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\RecipeVersion;
use App\Models\StockBalance;

/**
 * Perhitungan HPP (Blueprint §12.3).
 *
 * Biaya bahan per satuan dasar:
 *   1. rata-rata tertimbang stok cabang bila ada;
 *   2. untuk hasil racikan tanpa stok: HPP teoritis dari resep turunannya;
 *   3. selain itu harga beli terakhir.
 *
 * Bila resep memakai sub-resep, yang dihitung hanya biaya sub-resep itu, tidak ditambah lagi
 * bahan mentah penyusunnya, sehingga tidak terjadi penghitungan ganda.
 */
class RecipeCostService
{
    protected array $memo = [];

    public function unitCost(InventoryItem $item, ?Branch $branch, array $visiting = []): float
    {
        $key = ($branch?->id ?? 0).':'.$item->id;
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        if ($branch) {
            $avg = (float) StockBalance::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->value('avg_cost');
            if ($avg > 0) {
                return $this->memo[$key] = $avg;
            }
        }

        if ($item->isProduced() && ! in_array($item->id, $visiting, true)) {
            $version = $item->producingRecipe?->currentVersion;
            if ($version) {
                return $this->memo[$key] = $this->breakdown($version, $branch, [...$visiting, $item->id])['per_yield'];
            }
        }

        return $this->memo[$key] = (float) $item->last_cost;
    }

    /**
     * @return array{lines: array<int, array{item: InventoryItem, base_quantity: float, unit_cost: float, cost: float, source: string}>, total: float, per_yield: float}
     */
    public function breakdown(RecipeVersion $version, ?Branch $branch, array $visiting = []): array
    {
        $version->loadMissing('items.item.baseUnit', 'items.item.producingRecipe.currentVersion');
        $lines = [];
        $total = 0.0;
        foreach ($version->items as $ri) {
            $unitCost = $this->unitCost($ri->item, $branch, $visiting);
            $cost = $ri->base_quantity * $unitCost;
            $total += $cost;
            $lines[] = [
                'item' => $ri->item,
                'quantity' => $ri->quantity,
                'unit' => $ri->unit,
                'base_quantity' => $ri->base_quantity,
                'unit_cost' => $unitCost,
                'cost' => $cost,
                'source' => $this->costSource($ri->item, $branch),
            ];
        }
        $yield = max($version->yield_quantity, 0.0001);

        return ['lines' => $lines, 'total' => round($total, 2), 'per_yield' => $total / $yield];
    }

    public function costSource(InventoryItem $item, ?Branch $branch): string
    {
        if ($branch && (float) StockBalance::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->value('avg_cost') > 0) {
            return 'stok';
        }

        return $item->isProduced() ? 'resep' : 'harga beli';
    }

    /** HPP per porsi menu di cabang, atau null bila menu belum punya resep. */
    public function menuCost(Menu $menu, ?Branch $branch): ?float
    {
        $version = $menu->recipe?->currentVersion;

        return $version ? round($this->breakdown($version, $branch)['per_yield'], 2) : null;
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
