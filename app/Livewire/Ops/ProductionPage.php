<?php

namespace App\Livewire\Ops;

use App\Domain\Inventory\InventoryException;
use App\Domain\Inventory\ProductionService;
use App\Domain\Inventory\StockService;
use App\Livewire\Concerns\TenantPage;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Produksi racikan')]
class ProductionPage extends Component
{
    use TenantPage, WithPagination;

    #[Url]
    public string $status = '';

    public bool $showCreate = false;

    public string $recipeId = '';

    public string $planned = '';

    public ?int $batchId = null;

    public array $actuals = [];

    public string $actualOutput = '';

    public string $varianceReason = '';

    public function mount(): void
    {
        $this->authorize('production.manage');
    }

    public function create(ProductionService $prod): void
    {
        $this->authorize('production.manage');
        $this->validate(['recipeId' => 'required|integer', 'planned' => 'required|numeric|gt:0'], [], ['recipeId' => 'racikan', 'planned' => 'rencana hasil']);
        try {
            $batch = $prod->createDraft(Recipe::findOrFail((int) $this->recipeId), (float) $this->planned, $this->branch());
        } catch (InventoryException $e) {
            $this->addError('recipeId', $e->getMessage());

            return;
        }
        $this->showCreate = false;
        $this->recipeId = $this->planned = '';
        $this->open($batch->id);
    }

    public function open(int $id): void
    {
        $batch = $this->batch($id);
        $this->batchId = $batch->id;
        $this->actuals = $batch->consumptions->mapWithKeys(fn ($c) => [$c->id => (string) (float) $c->actual_quantity])->all();
        $this->actualOutput = $batch->status === 'draft' ? (string) (float) $batch->planned_output : (string) (float) $batch->actual_output;
        $this->varianceReason = (string) $batch->variance_reason;
        $this->resetValidation();
    }

    protected function batch(int $id): ProductionBatch
    {
        return ProductionBatch::with(['consumptions.item.baseUnit', 'outputItem.baseUnit', 'version.recipe'])
            ->where('branch_id', $this->branch()->id)->findOrFail($id);
    }

    public function post(ProductionService $prod): void
    {
        $this->authorize('production.manage');
        $this->validate([
            'actualOutput' => 'required|numeric|gt:0',
            'actuals.*' => 'required|numeric|min:0',
            'varianceReason' => 'nullable|string|max:255',
        ], [], ['actualOutput' => 'hasil aktual', 'actuals.*' => 'pemakaian aktual']);
        try {
            $batch = $prod->post($this->batch($this->batchId), (float) $this->actualOutput,
                array_map('floatval', $this->actuals), $this->varianceReason ?: null);
        } catch (InventoryException $e) {
            $this->addError('actualOutput', $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: "Batch {$batch->number} diposting. Stok racikan bertambah ".qty($batch->actual_output).' '.$batch->outputItem->unitCode().'.');
        $this->open($batch->id);
    }

    public function cancel(ProductionService $prod): void
    {
        $this->authorize('production.manage');
        try {
            $prod->cancel($this->batch($this->batchId));
        } catch (InventoryException $e) {
            $this->dispatch('toast', message: $e->getMessage());

            return;
        }
        $this->batchId = null;
        $this->dispatch('toast', message: 'Batch dibatalkan.');
    }

    public function render(StockService $stock)
    {
        $branch = $this->branch();
        $batch = $this->batchId ? $this->batch($this->batchId) : null;

        return view('livewire.ops.production', [
            'batches' => ProductionBatch::with(['outputItem.baseUnit'])->where('branch_id', $branch->id)
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->latest('id')->paginate(20),
            'recipes' => Recipe::with('outputItem.baseUnit', 'currentVersion')->where('type', 'sub')->whereNotNull('current_version_id')->orderBy('name')->get(),
            'batch' => $batch,
            'available' => $batch ? $batch->consumptions->mapWithKeys(fn ($c) => [$c->id => $stock->quantity($branch, $c->item)])->all() : [],
            'branch' => $branch,
        ]);
    }
}
