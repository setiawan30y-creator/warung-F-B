<?php

namespace App\Domain\Inventory;

use App\Models\InventoryItem;
use App\Models\Unit;

/** Mengubah kuantitas ke satuan dasar bahan (Blueprint §12.2). */
class UnitConverter
{
    /** Faktor 1 satuan → satuan dasar bahan, atau null bila tidak bisa dikonversi. */
    public function factor(InventoryItem $item, Unit|int $unit): ?float
    {
        $unit = $unit instanceof Unit ? $unit : Unit::find($unit);
        $base = $item->baseUnit;
        if (! $unit || ! $base) {
            return null;
        }
        if ($unit->id === $base->id) {
            return 1.0;
        }
        $conv = $item->conversions->firstWhere('unit_id', $unit->id);
        if ($conv) {
            return (float) $conv->factor;
        }
        if ($unit->dimension === $base->dimension && $unit->factor && $base->factor && $unit->dimension !== 'pack') {
            return $unit->factor / $base->factor;
        }

        return null;
    }

    public function toBase(InventoryItem $item, float $quantity, Unit|int $unit): float
    {
        $factor = $this->factor($item, $unit);
        if ($factor === null) {
            $code = ($unit instanceof Unit ? $unit : Unit::find($unit))?->name ?? 'satuan ini';
            throw new InventoryException("{$item->name} tidak bisa dihitung dalam {$code}. Atur isi kemasan di data bahan.");
        }

        return round($quantity * $factor, 4);
    }

    /**
     * Peta pilihan satuan per bahan untuk formulir: [item_id => [[id, code], ...]].
     *
     * @param  iterable<InventoryItem>  $items
     */
    public function optionsMap(iterable $items): array
    {
        $units = Unit::available()->orderBy('id')->get();
        $map = [];
        foreach ($items as $item) {
            $item->loadMissing('baseUnit', 'conversions');
            $map[$item->id] = $units->filter(fn (Unit $u) => $this->factor($item, $u) !== null)
                ->map(fn (Unit $u) => ['id' => $u->id, 'code' => $u->code])->values()->all();
        }

        return $map;
    }

    /** Satuan yang bisa dipakai untuk bahan ini. */
    public function unitsFor(InventoryItem $item)
    {
        $item->loadMissing('baseUnit', 'conversions.unit');

        return Unit::available()->orderBy('id')->get()
            ->filter(fn (Unit $u) => $this->factor($item, $u) !== null)
            ->values();
    }
}
