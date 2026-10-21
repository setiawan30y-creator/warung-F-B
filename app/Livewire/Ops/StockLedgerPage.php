<?php

namespace App\Livewire\Ops;

use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Kartu stok')]
class StockLedgerPage extends Component
{
    use TenantPage, WithPagination;

    #[Url]
    public string $item = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['item', 'type', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $branch = $this->branch();
        $base = StockMovement::where('branch_id', $branch->id)
            ->when($this->item !== '', fn ($q) => $q->where('inventory_item_id', (int) $this->item))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'));

        return view('livewire.ops.ledger', [
            'movements' => (clone $base)->with(['item.baseUnit', 'user:id,name'])->latest('id')->paginate(30),
            'totals' => ['in' => (clone $base)->where('quantity', '>', 0)->sum('total_cost'), 'out' => (clone $base)->where('quantity', '<', 0)->sum('total_cost')],
            'items' => InventoryItem::orderBy('name')->get(['id', 'name']),
            'selected' => $this->item !== '' ? InventoryItem::with('baseUnit')->find((int) $this->item) : null,
            'branch' => $branch,
        ]);
    }
}
