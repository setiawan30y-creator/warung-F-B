<?php

namespace App\Domain\Inventory;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WasteRecord;
use Illuminate\Support\Facades\DB;

/** Pencatatan susut dengan persetujuan berdasarkan nilai (Blueprint §13.2). */
class WasteService
{
    public function __construct(protected UnitConverter $units, protected StockService $stock, protected RecipeCostService $costs) {}

    public function record(Branch $branch, InventoryItem $item, float $quantity, int $unitId, string $reason, ?string $notes, User $user): WasteRecord
    {
        if (! array_key_exists($reason, WasteRecord::REASONS)) {
            throw new InventoryException('Pilih alasan susut.');
        }
        if ($quantity <= 0) {
            throw new InventoryException('Jumlah susut harus lebih dari nol.');
        }
        if ($reason === 'lainnya' && trim((string) $notes) === '') {
            throw new InventoryException('Tulis keterangan untuk alasan "Lainnya".');
        }
        $item->loadMissing('baseUnit', 'conversions');
        $base = $this->units->toBase($item, $quantity, $unitId);
        $value = round($base * $this->costs->unitCost($item, $branch), 2);
        $needsApproval = $value > (float) config('inventory.waste_approval_threshold') && ! $user->can('inventory.approve');

        return DB::transaction(function () use ($branch, $item, $quantity, $unitId, $base, $reason, $notes, $value, $needsApproval, $user) {
            $record = WasteRecord::create([
                'branch_id' => $branch->id, 'inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_id' => $unitId,
                'base_quantity' => $base, 'reason' => $reason, 'notes' => $notes ?: null, 'estimated_value' => $value,
                'status' => $needsApproval ? 'pending' : 'approved', 'created_by' => $user->id,
                'decided_by' => $needsApproval ? null : $user->id, 'decided_at' => $needsApproval ? null : now(),
            ]);
            if (! $needsApproval) {
                $this->stock->move($branch, $item, -$base, 'waste', reference: $record, reason: $record->reasonLabel());
            }

            return $record;
        });
    }

    public function approve(WasteRecord $record, User $approver, ?string $note = null): void
    {
        $this->guardDecision($record, $approver);
        DB::transaction(function () use ($record, $approver, $note) {
            $record->update(['status' => 'approved', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->stock->move($record->branch()->firstOrFail(), $record->item, -$record->base_quantity, 'waste', reference: $record, reason: $record->reasonLabel());
        });
    }

    public function reject(WasteRecord $record, User $approver, string $note): void
    {
        $this->guardDecision($record, $approver);
        if (trim($note) === '') {
            throw new InventoryException('Tulis alasan penolakan.');
        }
        $record->update(['status' => 'rejected', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    protected function guardDecision(WasteRecord $record, User $approver): void
    {
        if ($record->status !== 'pending') {
            throw new InventoryException('Catatan susut ini sudah diputuskan.');
        }
        if (! $approver->can('inventory.approve')) {
            throw new InventoryException('Anda tidak berwenang menyetujui susut.');
        }
        if ($record->created_by === $approver->id) {
            throw new InventoryException('Susut tidak dapat disetujui oleh pencatatnya sendiri.');
        }
    }
}
