<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Purchase order dan penerimaan barang (Blueprint §13.3). Utang & pembayaran supplier menyusul di Fase 5. */
class PurchasingService
{
    public function __construct(protected UnitConverter $units, protected StockService $stock, protected DocumentNumber $numbers) {}

    /**
     * @param  array<int, array{inventory_item_id:int, quantity:float, unit_id:int, unit_price:float}>  $lines
     */
    public function savePurchaseOrder(?PurchaseOrder $po, Branch $branch, Supplier $supplier, array $lines, string $orderDate, ?string $expectedDate, ?string $notes): PurchaseOrder
    {
        if ($po && $po->status !== 'draft') {
            throw new InventoryException('Hanya PO draf yang dapat diubah.');
        }
        if ($lines === []) {
            throw new InventoryException('PO minimal berisi satu bahan.');
        }

        return DB::transaction(function () use ($po, $branch, $supplier, $lines, $orderDate, $expectedDate, $notes) {
            $po ??= new PurchaseOrder(['branch_id' => $branch->id, 'number' => $this->numbers->next('PO', $branch), 'status' => 'draft', 'created_by' => Auth::id()]);
            $po->fill(['supplier_id' => $supplier->id, 'order_date' => $orderDate, 'expected_date' => $expectedDate ?: null, 'notes' => $notes ?: null]);
            $po->save();
            $po->items()->delete();

            $total = 0;
            foreach ($lines as $l) {
                $item = InventoryItem::with('baseUnit', 'conversions')->findOrFail($l['inventory_item_id']);
                if ($item->isProduced()) {
                    throw new InventoryException("{$item->name} adalah hasil racikan dan tidak dibeli dari supplier.");
                }
                if ((float) $l['quantity'] <= 0 || (float) $l['unit_price'] < 0) {
                    throw new InventoryException("Jumlah dan harga {$item->name} tidak valid.");
                }
                $line = $po->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => round((float) $l['quantity'], 4),
                    'unit_id' => (int) $l['unit_id'],
                    'unit_price' => round((float) $l['unit_price'], 2),
                    'base_quantity' => $this->units->toBase($item, (float) $l['quantity'], (int) $l['unit_id']),
                ]);
                $total += $line->lineTotal();
            }
            $po->update(['total' => round($total, 2)]);

            return $po->fresh('items');
        });
    }

    public function markOrdered(PurchaseOrder $po): void
    {
        if ($po->status !== 'draft') {
            throw new InventoryException('PO ini sudah dipesan.');
        }
        $po->update(['status' => 'ordered']);
    }

    public function cancel(PurchaseOrder $po): void
    {
        if (! in_array($po->status, ['draft', 'ordered'], true) || $po->receipts()->exists()) {
            throw new InventoryException('PO yang sudah diterima tidak bisa dibatalkan.');
        }
        $po->update(['status' => 'cancelled']);
    }

    /**
     * Terima barang. Kuantitas dalam satuan beli pada PO.
     *
     * @param  array<int, float>  $quantities  purchase_order_item_id => jumlah diterima
     */
    public function receive(PurchaseOrder $po, array $quantities, ?string $notes = null): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $quantities, $notes) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! $po->canReceive()) {
                throw new InventoryException('PO ini tidak dalam status bisa diterima.');
            }
            $branch = $po->branch;
            $receipt = GoodsReceipt::create([
                'branch_id' => $branch->id, 'purchase_order_id' => $po->id,
                'number' => $this->numbers->next('GRN', $branch), 'received_at' => now(),
                'notes' => $notes, 'received_by' => Auth::id(),
            ]);

            $total = 0;
            $any = false;
            foreach ($po->items()->with('item.baseUnit')->get() as $line) {
                $q = (float) ($quantities[$line->id] ?? 0);
                if ($q <= 0) {
                    continue;
                }
                $base = round($q * ($line->base_quantity / $line->quantity), 4);
                if ($base > $line->remainingBase() + StockService::EPS) {
                    throw new InventoryException("Jumlah diterima {$line->item->name} melebihi sisa pesanan.");
                }
                $unitCost = $line->baseUnitCost();
                $this->stock->move($branch, $line->item, $base, 'purchase_receipt', $unitCost, $receipt, $po->number);
                $line->update(['received_base_quantity' => round($line->received_base_quantity + $base, 4)]);
                $line->item->update(['last_cost' => round($unitCost, 6)]);
                $cost = round($base * $unitCost, 2);
                $receipt->items()->create([
                    'purchase_order_item_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id,
                    'base_quantity' => $base, 'unit_cost' => round($unitCost, 6), 'total_cost' => $cost,
                ]);
                $total += $cost;
                $any = true;
            }
            if (! $any) {
                throw new InventoryException('Isi jumlah yang diterima minimal untuk satu bahan.');
            }

            $receipt->update(['total' => round($total, 2)]);
            $done = $po->items()->get()->every(fn ($l) => $l->remainingBase() <= StockService::EPS);
            $po->update(['status' => $done ? 'received' : 'partial']);

            return $receipt;
        });
    }
}
