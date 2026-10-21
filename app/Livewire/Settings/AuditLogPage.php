<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\TenantPage;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Log audit')]
class AuditLogPage extends Component
{
    use TenantPage, WithPagination;

    #[Url]
    public string $event = '';

    #[Url]
    public string $userId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $detailId = null;

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['event', 'userId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function show(int $id): void
    {
        $this->detailId = AuditLog::findOrFail($id)->id; // tenant scope
    }

    public function render()
    {
        $logs = AuditLog::with(['user:id,name', 'branch:id,name'])
            ->when($this->event !== '', fn ($q) => $q->where('event', 'like', $this->event.'%'))
            ->when($this->userId !== '', fn ($q) => $q->where('user_id', (int) $this->userId))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(25);

        return view('livewire.settings.audit', [
            'logs' => $logs,
            'users' => User::ofTenant($this->tenant())->orderBy('name')->get(['id', 'name']),
            'detail' => $this->detailId ? AuditLog::with('user:id,name')->find($this->detailId) : null,
            'events' => [
                'auth' => 'Masuk/keluar', 'user' => 'Pengguna', 'role' => 'Peran', 'branch' => 'Cabang',
                'company_profile' => 'Profil perusahaan', 'theme_setting' => 'Tema', 'tenant_subscription' => 'Langganan',
            ],
        ]);
    }
}
