<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockCount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Stok opname: bandingkan stok sistem dengan hitungan fisik, lalu posting selisihnya. */
class StockCountService
{
    public function __construct(protected StockService $stock, protected DocumentNumber $numbers, protected RecipeCostService $costs) {}

    public function start(Branch $branch, ?string $category = null, ?string $notes = null): StockCount
    {
        $items = InventoryItem::where('is_active', true)->when($category, fn ($q) => $q->where('category', $category))->orderBy('name')->get();
        if ($items->isEmpty()) {
            throw new InventoryException('Tidak ada bahan untuk dihitung.');
        }

        return DB::transaction(function () use ($branch, $items, $category, $notes) {
            $count = StockCount::create([
                'branch_id' => $branch->id, 'number' => $this->numbers->next('SO', $branch), 'status' => 'draft',
                'category' => $category, 'notes' => $notes, 'created_by' => Auth::id(),
            ]);
            foreach ($items as $item) {
                $count->lines()->create(['inventory_item_id' => $item->id, 'system_quantity' => $this->stock->quantity($branch, $item)]);
            }

            return $count;
        });
    }

    /** @param array<int, float|string|null> $counted line_id => jumlah fisik (kosong = tidak dihitung) */
    public function saveCounts(StockCount $count, array $counted): void
    {
        if ($count->status !== 'draft') {
            throw new InventoryException('Opname ini sudah diposting.');
        }
        foreach ($count->lines as $line) {
            if (! array_key_exists($line->id, $counted)) {
                continue;
            }
            $v = $counted[$line->id];
            if ($v !== null && $v !== '' && (float) $v < 0) {
                throw new InventoryException('Jumlah fisik tidak boleh negatif.');
            }
            $line->update(['counted_quantity' => ($v === null || $v === '') ? null : round((float) $v, 4)]);
        }
    }

    public function post(StockCount $count): StockCount
    {
        return DB::transaction(function () use ($count) {
            $count = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            if ($count->status !== 'draft') {
                throw new InventoryException('Opname ini sudah diposting.');
            }
            $branch = Branch::findOrFail($count->branch_id);
            $lines = $count->lines()->with('item.baseUnit')->whereNotNull('counted_quantity')->get();
            if ($lines->isEmpty()) {
                throw new InventoryException('Belum ada jumlah fisik yang diisi.');
            }
            $total = 0.0;
            foreach ($lines as $line) {
                $system = $this->stock->quantity($branch, $line->item); // saldo saat posting
                $variance = round($line->counted_quantity - $system, 4);
                $unitCost = $this->costs->unitCost($line->item, $branch);
                if (abs($variance) > StockService::EPS) {
                    $m = $this->stock->move($branch, $line->item, $variance, 'stock_count', $variance > 0 ? $unitCost : null, $count, $count->number, allowNegative: true);
                    $unitCost = $m->unit_cost;
                }
                $value = round($variance * $unitCost, 2);
                $total += $value;
                $line->update(['posted_system_quantity' => $system, 'variance' => $variance, 'unit_cost' => round($unitCost, 6), 'variance_value' => $value]);
            }
            $count->update(['status' => 'posted', 'variance_value' => round($total, 2), 'posted_by' => Auth::id(), 'posted_at' => now()]);

            return $count->fresh();
        });
    }
}
