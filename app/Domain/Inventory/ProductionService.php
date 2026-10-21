<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Produksi batch sub-resep (Blueprint §11.3, §12.1.1): bahan baku berkurang sesuai konsumsi
 * aktual, hasil racikan bertambah sesuai yield aktual, selisih dicatat dengan alasan.
 */
class ProductionService
{
    public function __construct(protected StockService $stock, protected DocumentNumber $numbers) {}

    public function createDraft(Recipe $recipe, float $plannedOutput, Branch $branch): ProductionBatch
    {
        if (! $recipe->isSub()) {
            throw new InventoryException('Produksi batch hanya untuk resep racikan.');
        }
        $version = $recipe->currentVersion ?? throw new InventoryException('Resep belum memiliki komposisi.');
        if ($plannedOutput <= 0) {
            throw new InventoryException('Rencana hasil harus lebih dari nol.');
        }

        return DB::transaction(function () use ($recipe, $version, $plannedOutput, $branch) {
            $factor = $plannedOutput / $version->yield_quantity;
            $batch = ProductionBatch::create([
                'branch_id' => $branch->id,
                'recipe_version_id' => $version->id,
                'output_item_id' => $recipe->output_item_id,
                'number' => $this->numbers->next('PRD', $branch),
                'status' => 'draft',
                'planned_output' => round($plannedOutput, 4),
                'expected_output' => round($plannedOutput, 4),
                'created_by' => Auth::id(),
            ]);
            foreach ($version->items as $ri) {
                $planned = round($ri->base_quantity * $factor, 4);
                $batch->consumptions()->create([
                    'inventory_item_id' => $ri->inventory_item_id,
                    'planned_quantity' => $planned,
                    'actual_quantity' => $planned,
                ]);
            }

            return $batch;
        });
    }

    /**
     * @param  array<int, float>  $actuals  consumption_id => kuantitas aktual (satuan dasar)
     */
    public function post(ProductionBatch $batch, float $actualOutput, array $actuals = [], ?string $varianceReason = null): ProductionBatch
    {
        if ($batch->status !== 'draft') {
            throw new InventoryException('Batch ini sudah diposting atau dibatalkan.');
        }
        if ($actualOutput <= 0) {
            throw new InventoryException('Hasil aktual harus lebih dari nol. Bila batch gagal total, catat sebagai susut.');
        }
        $tolerance = (float) config('inventory.production_variance_tolerance');
        $variance = abs($actualOutput - $batch->planned_output) / max($batch->planned_output, 0.0001);
        if ($variance > $tolerance && trim((string) $varianceReason) === '') {
            throw new InventoryException(sprintf(
                'Hasil aktual berbeda %s%% dari rencana. Isi alasan selisih.', number_format($variance * 100, 1, ',', '.')
            ));
        }

        return DB::transaction(function () use ($batch, $actualOutput, $actuals, $varianceReason) {
            $batch = ProductionBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'draft') {
                throw new InventoryException('Batch ini sudah diposting.');
            }
            $branch = $batch->branch;
            $total = 0.0;

            foreach ($batch->consumptions()->with('item.baseUnit')->get() as $c) {
                $actual = round((float) ($actuals[$c->id] ?? $c->actual_quantity), 4);
                if ($actual < 0) {
                    throw new InventoryException("Pemakaian {$c->item->name} tidak boleh negatif.");
                }
                if ($actual > 0) {
                    $m = $this->stock->move($branch, $c->item, -$actual, 'production_consume', reference: $batch, reason: $batch->number);
                    $cost = -$m->total_cost;
                    $c->update(['actual_quantity' => $actual, 'unit_cost' => $m->unit_cost, 'total_cost' => $cost]);
                    $total += $cost;
                } else {
                    $c->update(['actual_quantity' => 0, 'unit_cost' => 0, 'total_cost' => 0]);
                }
            }

            $unitCost = $total / $actualOutput;
            $this->stock->move($branch, $batch->outputItem, $actualOutput, 'production_output', $unitCost, $batch, $batch->number);

            $batch->update([
                'status' => 'posted',
                'actual_output' => round($actualOutput, 4),
                'total_cost' => round($total, 2),
                'unit_cost' => round($unitCost, 6),
                'variance_reason' => $varianceReason ?: null,
                'produced_at' => now(),
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            return $batch->fresh();
        });
    }

    public function cancel(ProductionBatch $batch): void
    {
        if ($batch->status !== 'draft') {
            throw new InventoryException('Hanya batch draf yang bisa dibatalkan.');
        }
        $batch->update(['status' => 'cancelled']);
    }
}
