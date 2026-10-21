<?php

namespace App\Livewire;

use App\Domain\Widgets\WeatherService;
use App\Livewire\Concerns\TenantPage;
use App\Models\Branch;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class WeatherWidget extends Component
{
    use TenantPage;

    public ?int $branchId = null;

    public function placeholder()
    {
        return '<div class="panel-body muted small">Memuat cuaca…</div>';
    }

    public function render(WeatherService $weather)
    {
        $branch = $this->branchId ? Branch::find($this->branchId) : null;
        abort_if($branch && ! $this->user()->canAccessBranch($branch), 403);

        return view('livewire.weather-widget', [
            'branch' => $branch,
            'weather' => $branch ? $weather->forBranch($branch) : ['status' => 'no_branch'],
        ]);
    }
}
