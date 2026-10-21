<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\UnitConverter;
use App\Domain\Inventory\WasteService;
use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\WasteRecord;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Susut & rusak')]
class WastePage extends Component
{
    use TenantPage, WithPagination;

    public array $form = ['item' => '', 'quantity' => '', 'unit' => '', 'reason' => '', 'notes' => ''];

    #[Url]
    public string $month = '';

    public ?int $rejectId = null;

    public string $rejectNote = '';

    public function mount(): void
    {
        $this->authorize('inventory.view');
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');
    }

    public function updatedForm($value, $key): void
    {
        if ($key === 'item') {
            $this->form['unit'] = (string) (InventoryItem::find((int) $value)?->base_unit_id ?? '');
        }
    }

    public function record(WasteService $waste): void
    {
        $this->authorize('inventory.manage');
        $this->validate([
            'form.item' => ['required', Rule::exists('inventory_items', 'id')->where('tenant_id', $this->tenant()->id)],
            'form.quantity' => 'required|numeric|gt:0',
            'form.unit' => 'required|integer',
            'form.reason' => ['required', Rule::in(array_keys(WasteRecord::REASONS))],
            'form.notes' => 'nullable|string|max:255',
        ], [], ['form.item' => 'bahan', 'form.quantity' => 'jumlah', 'form.unit' => 'satuan', 'form.reason' => 'alasan']);

        try {
            $rec = $waste->record($this->branch(), InventoryItem::findOrFail((int) $this->form['item']), (float) $this->form['quantity'],
                (int) $this->form['unit'], $this->form['reason'], $this->form['notes'], $this->user());
        } catch (InventoryException $e) {
            $this->addError('form.quantity', $e->getMessage());

            return;
        }
        $this->form = ['item' => '', 'quantity' => '', 'unit' => '', 'reason' => '', 'notes' => ''];
        $this->dispatch('toast', message: $rec->status === 'pending'
            ? 'Susut dicatat dan menunggu persetujuan karena nilainya '.rupiah($rec->estimated_value).'.'
            : 'Susut dicatat dan stok dikurangi.');
    }

    public function approve(int $id, WasteService $waste): void
    {
        $this->authorize('inventory.approve');
        try {
            $waste->approve($this->pending($id), $this->user());
            $this->dispatch('toast', message: 'Susut disetujui dan stok dikurangi.');
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());
        }
    }

    public function reject(WasteService $waste): void
    {
        $this->authorize('inventory.approve');
        try {
            $waste->reject($this->pending($this->rejectId), $this->user(), $this->rejectNote);
        } catch (InventoryException $e) {
            $this->addError('rejectNote', $e->getMessage());

            return;
        }
        $this->rejectId = null;
        $this->rejectNote = '';
        $this->dispatch('toast', message: 'Susut ditolak. Stok tidak berubah.');
    }

    protected function pending(int $id): WasteRecord
    {
        return WasteRecord::where('branch_id', $this->branch()->id)->findOrFail($id);
    }

    public function render(UnitConverter $units)
    {
        $branch = $this->branch();
        [$y, $m] = explode('-', $this->month);
        $start = now()->setDate((int) $y, (int) $m, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $period = WasteRecord::where('branch_id', $branch->id)->where('status', 'approved')->whereBetween('created_at', [$start, $end]);
        $items = InventoryItem::with('baseUnit', 'conversions')->where('is_active', true)->orderBy('name')->get();
        $selected = $items->firstWhere('id', (int) $this->form['item']);

        return view('livewire.ops.waste', [
            'items' => $items,
            'unitOptions' => $selected ? $units->optionsMap([$selected])[$selected->id] : [],
            'pending' => WasteRecord::with(['item.baseUnit', 'unit', 'creator:id,name'])->where('branch_id', $branch->id)->where('status', 'pending')->latest('id')->get(),
            'history' => WasteRecord::with(['item.baseUnit', 'unit', 'creator:id,name', 'decider:id,name'])->where('branch_id', $branch->id)
                ->where('status', '!=', 'pending')->whereBetween('created_at', [$start, $end])->latest('id')->paginate(20),
            'byReason' => (clone $period)->selectRaw('reason, SUM(estimated_value) as total, COUNT(*) as n')->groupBy('reason')->orderByDesc('total')->get(),
            'byItem' => (clone $period)->with('item:id,name')->selectRaw('inventory_item_id, SUM(estimated_value) as total')->groupBy('inventory_item_id')->orderByDesc('total')->limit(5)->get(),
            'periodTotal' => (clone $period)->sum('estimated_value'),
            'threshold' => (float) config('inventory.waste_approval_threshold'),
            'canManage' => $this->user()->can('inventory.manage'),
            'canApprove' => $this->user()->can('inventory.approve'),
            'branch' => $branch,
            'periodLabel' => $start->translatedFormat('F Y'),
        ]);
    }
}
