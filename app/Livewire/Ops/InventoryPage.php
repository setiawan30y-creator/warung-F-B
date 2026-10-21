<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\RecipeCostService;
use App\Domain\Inventory\StockService;
use App\Domain\Inventory\UnitConverter;
use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\ItemUnitConversion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Bahan & stok')]
class InventoryPage extends Component
{
    use TenantPage;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $type = '';

    #[Url]
    public bool $lowOnly = false;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public array $conversions = [];

    public ?int $adjustId = null;

    public array $adjust = ['direction' => 'in', 'quantity' => '', 'unit' => '', 'unit_cost' => '', 'reason' => ''];

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    protected function resetForm(): void
    {
        $this->form = ['code' => '', 'name' => '', 'category' => '', 'base_unit_id' => '', 'min_stock' => '0', 'last_cost' => '', 'is_active' => true];
        $this->conversions = [];
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventory.manage');
        $item = InventoryItem::with('conversions')->findOrFail($id);
        $this->editingId = $item->id;
        $this->resetForm();
        $this->form = ['code' => $item->code, 'name' => $item->name, 'category' => (string) $item->category,
            'base_unit_id' => (string) $item->base_unit_id, 'min_stock' => (string) (float) $item->min_stock,
            'last_cost' => (string) (float) $item->last_cost, 'is_active' => $item->is_active];
        $this->conversions = $item->conversions->map(fn ($c) => ['unit' => (string) $c->unit_id, 'factor' => (string) (float) $c->factor])->all();
        $this->showForm = true;
    }

    public function addConversion(): void
    {
        $this->conversions[] = ['unit' => '', 'factor' => ''];
    }

    public function removeConversion(int $i): void
    {
        unset($this->conversions[$i]);
        $this->conversions = array_values($this->conversions);
    }

    public function save(): void
    {
        $this->authorize('inventory.manage');
        $tenantId = $this->tenant()->id;
        $item = $this->editingId ? InventoryItem::findOrFail($this->editingId) : null;
        $hasMovements = $item && StockMovement::where('inventory_item_id', $item->id)->exists();

        $this->validate([
            'form.code' => ['required', 'alpha_dash', 'max:30', Rule::unique('inventory_items', 'code')->where('tenant_id', $tenantId)->ignore($this->editingId)],
            'form.name' => 'required|string|max:120',
            'form.category' => 'nullable|string|max:60',
            'form.base_unit_id' => ['required', Rule::in(Unit::available()->base()->pluck('id')->all())],
            'form.min_stock' => 'required|numeric|min:0',
            'form.last_cost' => 'nullable|numeric|min:0',
            'form.is_active' => 'boolean',
            'conversions' => 'array',
            'conversions.*.unit' => ['required', 'distinct', Rule::in(Unit::available()->pluck('id')->all())],
            'conversions.*.factor' => 'required|numeric|gt:0',
        ], [], ['form.code' => 'kode', 'form.name' => 'nama bahan', 'form.base_unit_id' => 'satuan dasar', 'form.min_stock' => 'stok minimum',
            'form.last_cost' => 'harga acuan', 'conversions.*.unit' => 'satuan kemasan', 'conversions.*.factor' => 'isi kemasan']);

        if ($hasMovements && (int) $this->form['base_unit_id'] !== $item->base_unit_id) {
            $this->addError('form.base_unit_id', 'Satuan dasar tidak bisa diganti karena bahan ini sudah punya riwayat stok.');

            return;
        }
        foreach ($this->conversions as $i => $c) {
            if ((int) $c['unit'] === (int) $this->form['base_unit_id']) {
                $this->addError("conversions.$i.unit", 'Satuan kemasan tidak boleh sama dengan satuan dasar.');

                return;
            }
        }

        DB::transaction(function () use ($item) {
            $f = $this->form;
            $item ??= new InventoryItem(['type' => 'purchased']);
            $item->fill(['code' => strtoupper($f['code']), 'name' => $f['name'], 'category' => $f['category'] ?: null,
                'base_unit_id' => (int) $f['base_unit_id'], 'min_stock' => round((float) $f['min_stock'], 4),
                'is_active' => (bool) $f['is_active']]);
            if (! $item->isProduced()) {
                $item->last_cost = round((float) ($f['last_cost'] ?: 0), 6);
            }
            $item->save();
            $item->conversions()->delete();
            foreach ($this->conversions as $c) {
                ItemUnitConversion::create(['inventory_item_id' => $item->id, 'unit_id' => (int) $c['unit'], 'factor' => round((float) $c['factor'], 4)]);
            }
        });

        $this->showForm = false;
        $this->dispatch('toast', message: $this->editingId ? 'Bahan diperbarui.' : 'Bahan ditambahkan.');
    }

    public function openAdjust(int $id): void
    {
        $this->authorize('inventory.manage');
        $item = InventoryItem::findOrFail($id);
        $isOpening = ! StockMovement::where('inventory_item_id', $item->id)->where('branch_id', $this->branch()->id)->exists();
        abort_unless($isOpening || $this->user()->can('inventory.approve'), 403, 'Penyesuaian stok perlu izin persetujuan. Gunakan stok opname.');
        $this->adjustId = $item->id;
        $this->adjust = ['direction' => 'in', 'quantity' => '', 'unit' => (string) $item->base_unit_id,
            'unit_cost' => $item->last_cost > 0 ? (string) (float) $item->last_cost : '', 'reason' => $isOpening ? 'Saldo awal' : ''];
        $this->resetValidation();
    }

    public function saveAdjust(StockService $stock, UnitConverter $units): void
    {
        $this->authorize('inventory.manage');
        $item = InventoryItem::with('baseUnit', 'conversions')->findOrFail($this->adjustId);
        $branch = $this->branch();
        $isOpening = ! StockMovement::where('inventory_item_id', $item->id)->where('branch_id', $branch->id)->exists();
        abort_unless($isOpening || $this->user()->can('inventory.approve'), 403);

        $this->validate([
            'adjust.direction' => 'required|in:in,out',
            'adjust.quantity' => 'required|numeric|gt:0',
            'adjust.unit' => 'required|integer',
            'adjust.unit_cost' => $this->adjust['direction'] === 'in' ? 'required|numeric|min:0' : 'nullable',
            'adjust.reason' => 'required|string|max:160',
        ], [], ['adjust.quantity' => 'jumlah', 'adjust.unit_cost' => 'harga per satuan', 'adjust.reason' => 'alasan']);

        try {
            $factor = $units->factor($item, (int) $this->adjust['unit']) ?? throw new InventoryException('Satuan tidak cocok dengan bahan ini.');
            $base = round((float) $this->adjust['quantity'] * $factor, 4);
            $sign = $this->adjust['direction'] === 'in' ? 1 : -1;
            $cost = $sign > 0 ? (float) $this->adjust['unit_cost'] / $factor : null;
            $stock->move($branch, $item, $sign * $base, $isOpening ? 'opening' : 'adjustment', $cost, reason: $this->adjust['reason']);
        } catch (InventoryException $e) {
            $this->addError('adjust.quantity', $e->getMessage());

            return;
        }
        $this->adjustId = null;
        $this->dispatch('toast', message: $isOpening ? 'Saldo awal dicatat.' : 'Penyesuaian stok dicatat.');
    }

    public function render(RecipeCostService $costs, UnitConverter $units)
    {
        $branch = $this->branch();
        $balances = StockBalance::where('branch_id', $branch->id)->get()->keyBy('inventory_item_id');

        $items = InventoryItem::with('baseUnit')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->orderByDesc('is_active')->orderBy('name')->get()
            ->map(function (InventoryItem $i) use ($balances, $costs, $branch) {
                $b = $balances[$i->id] ?? null;
                $q = (float) ($b->quantity ?? 0);
                $unitCost = $b && $b->avg_cost > 0 ? (float) $b->avg_cost : $costs->unitCost($i, $branch);

                return ['item' => $i, 'qty' => $q, 'unit_cost' => $unitCost, 'value' => $q * $unitCost,
                    'status' => $q <= 0 ? 'out' : ($i->min_stock > 0 && $q <= $i->min_stock ? 'low' : 'ok')];
            })
            ->when($this->lowOnly, fn ($c) => $c->filter(fn ($r) => $r['item']->is_active && $r['status'] !== 'ok'))
            ->values();

        $adjustItem = $this->adjustId ? InventoryItem::with('baseUnit', 'conversions')->find($this->adjustId) : null;

        return view('livewire.ops.inventory', [
            'rows' => $items,
            'totalValue' => $items->sum('value'),
            'lowCount' => $items->where('status', '!=', 'ok')->filter(fn ($r) => $r['item']->is_active)->count(),
            'categories' => InventoryItem::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'baseUnits' => Unit::available()->base()->orderBy('id')->get(),
            'allUnits' => Unit::available()->orderBy('id')->get(),
            'branch' => $branch,
            'canManage' => $this->user()->can('inventory.manage'),
            'canApprove' => $this->user()->can('inventory.approve'),
            'adjustItem' => $adjustItem,
            'adjustUnits' => $adjustItem ? $units->optionsMap([$adjustItem])[$adjustItem->id] : [],
            'adjustIsOpening' => $adjustItem && ! StockMovement::where('inventory_item_id', $adjustItem->id)->where('branch_id', $branch->id)->exists(),
            'movedItemIds' => StockMovement::where('branch_id', $branch->id)->distinct()->pluck('inventory_item_id')->flip(),
        ]);
    }
}
