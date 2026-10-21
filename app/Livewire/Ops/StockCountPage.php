<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\StockCountService;
use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\StockCount;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Stok opname')]
class StockCountPage extends Component
{
    use TenantPage, WithPagination;

    public bool $showCreate = false;

    public string $category = '';

    public string $notes = '';

    public ?int $countId = null;

    public array $counted = [];

    public function mount(): void
    {
        $this->authorize('inventory.manage');
    }

    public function start(StockCountService $svc): void
    {
        $this->authorize('inventory.manage');
        try {
            $count = $svc->start($this->branch(), $this->category ?: null, $this->notes ?: null);
        } catch (InventoryException $e) {
            $this->addError('category', $e->getMessage());

            return;
        }
        $this->showCreate = false;
        $this->category = $this->notes = '';
        $this->open($count->id);
    }

    public function open(int $id): void
    {
        $count = $this->count($id);
        $this->countId = $count->id;
        $this->counted = $count->lines->mapWithKeys(fn ($l) => [$l->id => $l->counted_quantity === null ? '' : (string) (float) $l->counted_quantity])->all();
    }

    protected function count(int $id): StockCount
    {
        return StockCount::with(['lines.item.baseUnit'])->where('branch_id', $this->branch()->id)->findOrFail($id);
    }

    public function saveCounts(StockCountService $svc): void
    {
        $this->authorize('inventory.manage');
        $this->validate(['counted.*' => 'nullable|numeric|min:0'], [], ['counted.*' => 'jumlah fisik']);
        try {
            $svc->saveCounts($this->count($this->countId), $this->counted);
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: 'Hitungan disimpan. Opname belum mengubah stok sampai diposting.');
    }

    public function post(StockCountService $svc): void
    {
        $this->authorize('inventory.approve');
        $this->validate(['counted.*' => 'nullable|numeric|min:0'], [], ['counted.*' => 'jumlah fisik']);
        try {
            $count = $this->count($this->countId);
            $svc->saveCounts($count, $this->counted);
            $count = $svc->post($count->fresh());
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: "Opname {$count->number} diposting. Selisih ".rupiah($count->variance_value).'.');
        $this->open($count->id);
    }

    public function render()
    {
        $branch = $this->branch();

        return view('livewire.ops.counts', [
            'counts' => StockCount::with('creator:id,name')->withCount(['lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity')])
                ->where('branch_id', $branch->id)->latest('id')->paginate(20),
            'count' => $this->countId ? $this->count($this->countId) : null,
            'categories' => InventoryItem::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'canPost' => $this->user()->can('inventory.approve'),
            'branch' => $branch,
        ]);
    }
}
