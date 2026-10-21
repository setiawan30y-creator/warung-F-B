<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok. Setiap perubahan menulis baris ledger dan memperbarui
 * saldo cabang dalam satu transaksi. Valuasi: rata-rata tertimbang per cabang.
 */
class StockService
{
    public const EPS = 0.00005;

    public function balance(Branch $branch, InventoryItem $item): ?StockBalance
    {
        return StockBalance::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->first();
    }

    public function quantity(Branch $branch, InventoryItem $item): float
    {
        return (float) ($this->balance($branch, $item)?->quantity ?? 0);
    }

    /**
     * @param  float  $quantity  dalam satuan dasar; positif = masuk, negatif = keluar
     * @param  float|null  $unitCost  biaya per satuan dasar untuk barang masuk; keluar memakai rata-rata
     */
    public function move(
        Branch $branch,
        InventoryItem $item,
        float $quantity,
        string $type,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $reason = null,
        bool $allowNegative = false,
    ): StockMovement {
        if (abs($quantity) < self::EPS) {
            throw new InventoryException('Jumlah tidak boleh nol.');
        }
        if ($branch->tenant_id !== $item->tenant_id) {
            throw new InventoryException('Bahan dan cabang berasal dari usaha berbeda.');
        }

        return DB::transaction(function () use ($branch, $item, $quantity, $type, $unitCost, $reference, $reason, $allowNegative) {
            $balance = StockBalance::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->lockForUpdate()->first()
                ?? StockBalance::create(['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'inventory_item_id' => $item->id, 'quantity' => 0, 'avg_cost' => 0]);

            $current = (float) $balance->quantity;
            $avg = (float) $balance->avg_cost;
            $new = round($current + $quantity, 4);

            if ($quantity > 0) {
                $cost = $unitCost ?? ($avg > 0 ? $avg : app(RecipeCostService::class)->unitCost($item, $branch));
                $avg = $current <= self::EPS ? $cost : (($current * $avg) + ($quantity * $cost)) / $new;
            } else {
                if (! $allowNegative && $new < -self::EPS) {
                    throw new InventoryException(sprintf(
                        'Stok %s di %s tidak cukup: tersedia %s %s, dibutuhkan %s %s.',
                        $item->name, $branch->name, qty($current), $item->unitCode(), qty(-$quantity), $item->unitCode()
                    ));
                }
                $cost = $avg > 0 ? $avg : app(RecipeCostService::class)->unitCost($item, $branch);
            }

            $balance->update(['quantity' => $new, 'avg_cost' => round($avg, 6)]);
            app(RecipeCostService::class)->forget();

            return StockMovement::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => round($quantity, 4),
                'unit_cost' => round($cost, 6),
                'total_cost' => round($quantity * $cost, 2),
                'balance_after' => $new,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
                'user_id' => Auth::id(),
            ]);
        });
    }

    /**
     * Bandingkan saldo dengan jumlah ledger (kriteria penerimaan §30 no. 6).
     *
     * @return array<int, array{branch_id:int, item_id:int, balance:float, ledger:float}>
     */
    public function reconcile(): array
    {
        $ledger = StockMovement::selectRaw('branch_id, inventory_item_id, SUM(quantity) as q')
            ->groupBy('branch_id', 'inventory_item_id')->get()
            ->keyBy(fn ($r) => $r->branch_id.':'.$r->inventory_item_id);

        $issues = [];
        foreach (StockBalance::all() as $b) {
            $l = (float) ($ledger[$b->branch_id.':'.$b->inventory_item_id]->q ?? 0);
            if (abs($l - $b->quantity) > 0.001) {
                $issues[] = ['branch_id' => $b->branch_id, 'item_id' => $b->inventory_item_id, 'balance' => (float) $b->quantity, 'ledger' => $l];
            }
        }

        return $issues;
    }
}
