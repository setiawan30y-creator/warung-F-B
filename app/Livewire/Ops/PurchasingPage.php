<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\PurchasingService;
use App\Domain\Inventory\UnitConverter;
use App\Livewire\Concerns\TenantPage;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pembelian')]
class PurchasingPage extends Component
{
    use TenantPage, WithPagination;

    #[Url]
    public string $tab = 'po';

    #[Url]
    public string $status = '';

    public bool $showPo = false;

    public ?int $poId = null;

    public array $po = [];

    public array $lines = [];

    public ?int $receiveId = null;

    public array $receive = [];

    public string $receiveNotes = '';

    public bool $showSupplier = false;

    public ?int $supplierId = null;

    public array $supplier = [];

    public function mount(): void
    {
        $this->authorize('purchasing.manage');
        $this->tab = in_array($this->tab, ['po', 'supplier'], true) ? $this->tab : 'po';
    }

    // ---------- PO ----------

    public function newPo(): void
    {
        $this->poId = null;
        $this->po = ['supplier_id' => '', 'order_date' => now()->toDateString(), 'expected_date' => '', 'notes' => ''];
        $this->lines = [['item' => '', 'quantity' => '', 'unit' => '', 'price' => '']];
        $this->resetValidation();
        $this->showPo = true;
    }

    public function editPo(int $id): void
    {
        $po = $this->findPo($id);
        if ($po->status !== 'draft') {
            return;
        }
        $this->poId = $po->id;
        $this->po = ['supplier_id' => (string) $po->supplier_id, 'order_date' => $po->order_date->toDateString(),
            'expected_date' => $po->expected_date?->toDateString() ?? '', 'notes' => (string) $po->notes];
        $this->lines = $po->items->map(fn ($l) => ['item' => (string) $l->inventory_item_id, 'quantity' => (string) (float) $l->quantity,
            'unit' => (string) $l->unit_id, 'price' => (string) (float) $l->unit_price])->all();
        $this->resetValidation();
        $this->showPo = true;
    }

    protected function findPo(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['items.item.baseUnit', 'items.unit', 'supplier', 'receipts'])->where('branch_id', $this->branch()->id)->findOrFail($id);
    }

    public function addLine(): void
    {
        $this->lines[] = ['item' => '', 'quantity' => '', 'unit' => '', 'price' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines) ?: [['item' => '', 'quantity' => '', 'unit' => '', 'price' => '']];
    }

    public function updatedLines($value, $key): void
    {
        [$i, $field] = array_pad(explode('.', (string) $key), 2, null);
        if ($field === 'item' && $value) {
            $item = InventoryItem::find((int) $value);
            $this->lines[(int) $i]['unit'] = (string) ($item?->base_unit_id ?? '');
            $this->lines[(int) $i]['price'] = $item && $item->last_cost > 0 ? (string) round($item->last_cost, 2) : '';
        }
        if ($field === 'unit' && $value && ($item = InventoryItem::with('baseUnit', 'conversions')->find((int) ($this->lines[(int) $i]['item'] ?? 0)))) {
            $f = app(UnitConverter::class)->factor($item, (int) $value);
            if ($f && $item->last_cost > 0) {
                $this->lines[(int) $i]['price'] = (string) round($item->last_cost * $f, 2);
            }
        }
    }

    public function savePo(PurchasingService $svc, bool $order = false): void
    {
        $this->authorize('purchasing.manage');
        $tenantId = $this->tenant()->id;
        $this->validate([
            'po.supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'po.order_date' => 'required|date',
            'po.expected_date' => 'nullable|date|after_or_equal:po.order_date',
            'po.notes' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.item' => ['required', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenantId)],
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit' => 'required|integer',
            'lines.*.price' => 'required|numeric|min:0',
        ], [], ['po.supplier_id' => 'supplier', 'po.order_date' => 'tanggal pesan', 'po.expected_date' => 'perkiraan tiba',
            'lines.*.item' => 'bahan', 'lines.*.quantity' => 'jumlah', 'lines.*.unit' => 'satuan', 'lines.*.price' => 'harga']);

        try {
            $po = $svc->savePurchaseOrder($this->poId ? $this->findPo($this->poId) : null, $this->branch(), Supplier::findOrFail((int) $this->po['supplier_id']),
                array_map(fn ($l) => ['inventory_item_id' => (int) $l['item'], 'quantity' => (float) $l['quantity'], 'unit_id' => (int) $l['unit'], 'unit_price' => (float) $l['price']], $this->lines),
                $this->po['order_date'], $this->po['expected_date'] ?: null, $this->po['notes'] ?: null);
            if ($order) {
                $svc->markOrdered($po);
            }
        } catch (InventoryException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }
        $this->showPo = false;
        $this->dispatch('toast', message: $order ? "{$po->number} dipesan ke {$po->supplier->name}." : "{$po->number} disimpan sebagai draf.");
    }

    public function orderPo(PurchasingService $svc): void
    {
        $this->savePo($svc, true);
    }

    public function markOrdered(int $id, PurchasingService $svc): void
    {
        $this->authorize('purchasing.manage');
        try {
            $svc->markOrdered($this->findPo($id));
            $this->dispatch('toast', message: 'PO ditandai sudah dipesan.');
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());
        }
    }

    public function cancelPo(int $id, PurchasingService $svc): void
    {
        $this->authorize('purchasing.manage');
        try {
            $svc->cancel($this->findPo($id));
            $this->dispatch('toast', message: 'PO dibatalkan.');
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());
        }
    }

    public function openReceive(int $id): void
    {
        $po = $this->findPo($id);
        if (! $po->canReceive()) {
            return;
        }
        $this->receiveId = $po->id;
        $this->receive = $po->items->mapWithKeys(fn ($l) => [$l->id => (string) round($l->remainingBase() * ($l->quantity / $l->base_quantity), 4)])->all();
        $this->receiveNotes = '';
        $this->resetValidation();
    }

    public function saveReceive(PurchasingService $svc): void
    {
        $this->authorize('purchasing.manage');
        $this->validate(['receive.*' => 'nullable|numeric|min:0', 'receiveNotes' => 'nullable|string|max:255'], [], ['receive.*' => 'jumlah diterima']);
        try {
            $grn = $svc->receive($this->findPo($this->receiveId), array_map(fn ($v) => (float) ($v ?: 0), $this->receive), $this->receiveNotes ?: null);
        } catch (InventoryException $e) {
            $this->addError('receive', $e->getMessage());

            return;
        }
        $this->receiveId = null;
        $this->dispatch('toast', message: "Barang diterima ({$grn->number}). Stok dan harga acuan bahan diperbarui.");
    }

    // ---------- Supplier ----------

    public function newSupplier(): void
    {
        $this->supplierId = null;
        $this->supplier = ['name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'address' => '', 'lead_time_days' => '', 'notes' => '', 'is_active' => true];
        $this->resetValidation();
        $this->showSupplier = true;
    }

    public function editSupplier(int $id): void
    {
        $s = Supplier::findOrFail($id);
        $this->supplierId = $s->id;
        $this->supplier = array_map(fn ($v) => $v ?? '', $s->only(['name', 'contact_name', 'phone', 'email', 'address', 'lead_time_days', 'notes'])) + ['is_active' => $s->is_active];
        $this->resetValidation();
        $this->showSupplier = true;
    }

    public function saveSupplier(): void
    {
        $this->authorize('purchasing.manage');
        $data = $this->validate([
            'supplier.name' => 'required|string|max:120',
            'supplier.contact_name' => 'nullable|string|max:120',
            'supplier.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'supplier.email' => 'nullable|email|max:160',
            'supplier.address' => 'nullable|string|max:255',
            'supplier.lead_time_days' => 'nullable|integer|min:0|max:365',
            'supplier.notes' => 'nullable|string|max:255',
            'supplier.is_active' => 'boolean',
        ], [], ['supplier.name' => 'nama supplier', 'supplier.phone' => 'telepon', 'supplier.lead_time_days' => 'waktu kirim'])['supplier'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $this->supplierId ? Supplier::findOrFail($this->supplierId)->update($data) : Supplier::create($data);
        $this->showSupplier = false;
        $this->dispatch('toast', message: 'Supplier disimpan.');
    }

    public function render(UnitConverter $units)
    {
        $branch = $this->branch();
        $items = InventoryItem::with('baseUnit', 'conversions')->where('type', 'purchased')->where('is_active', true)->orderBy('name')->get();

        return view('livewire.ops.purchasing', [
            'orders' => PurchaseOrder::with('supplier:id,name')->withCount('items')->where('branch_id', $branch->id)
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->latest('id')->paginate(20),
            'suppliers' => Supplier::withCount('purchaseOrders')->orderByDesc('is_active')->orderBy('name')->get(),
            'items' => $items,
            'unitMap' => $this->showPo ? $units->optionsMap($items) : [],
            'receivePo' => $this->receiveId ? $this->findPo($this->receiveId) : null,
            'poTotal' => collect($this->lines)->sum(fn ($l) => is_numeric($l['quantity'] ?? null) && is_numeric($l['price'] ?? null) ? $l['quantity'] * $l['price'] : 0),
            'branch' => $branch,
        ]);
    }
}
