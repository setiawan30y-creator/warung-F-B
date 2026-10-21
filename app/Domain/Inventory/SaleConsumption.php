<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pengurangan stok saat menu terjual. Dipanggil oleh POS (Fase 3).
 * Sub-resep yang dipakai menu mengurangi stok racikannya, bukan bahan mentahnya.
 * Mengembalikan HPP snapshot untuk disimpan di baris penjualan.
 */
class SaleConsumption
{
    public function __construct(protected StockService $stock) {}

    public function consume(Menu $menu, float $portions, Branch $branch, ?Model $reference = null): float
    {
        $version = $menu->recipe?->currentVersion;
        if (! $version) {
            return 0.0; // menu tanpa resep tidak memotong stok
        }

        return DB::transaction(function () use ($version, $portions, $branch, $reference, $menu) {
            $factor = $portions / $version->yield_quantity;
            $cost = 0.0;
            foreach ($version->items()->with('item.baseUnit')->get() as $ri) {
                $m = $this->stock->move($branch, $ri->item, -round($ri->base_quantity * $factor, 4), 'sale', reference: $reference,
                    reason: $menu->name, allowNegative: (bool) config('inventory.allow_negative_on_sale'));
                $cost += -$m->total_cost;
            }

            return round($cost, 2);
        });
    }
}
