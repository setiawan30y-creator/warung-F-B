<div class="stack">
    <div class="page-head">
        <div>
            <a class="small" href="{{ route('ops.inventory') }}" wire:navigate>Kembali ke bahan dan stok</a>
            <h1 style="margin-top:6px">Kartu stok</h1>
            <p>Setiap barang masuk dan keluar di {{ $branch->name }}, lengkap dengan biaya saat itu. Catatan ini tidak dapat diubah; koreksi dilakukan lewat penyesuaian atau opname.</p>
        </div>
    </div>

    <section class="panel">
        <div class="filters">
            <div class="field" style="min-width:220px">
                <label for="l-item">Bahan</label>
                <select id="l-item" class="select" wire:model.live="item">
                    <option value="">Semua bahan</option>
                    @foreach ($items as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="l-type">Jenis</label>
                <select id="l-type" class="select" wire:model.live="type">
                    <option value="">Semua</option>
                    @foreach (\App\Models\StockMovement::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label for="l-from">Dari</label><input id="l-from" class="input" type="date" wire:model.live="from"></div>
            <div class="field"><label for="l-to">Sampai</label><input id="l-to" class="input" type="date" wire:model.live="to"></div>
            <div class="right small" style="padding-bottom:8px">
                Masuk <strong class="pos">{{ rupiah($totals['in']) }}</strong>, keluar <strong class="neg">{{ rupiah(abs($totals['out'])) }}</strong>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Bahan</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Biaya per satuan</th><th class="num">Nilai</th><th class="num">Saldo</th><th>Keterangan</th></tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    @php $u = $m->item->unitCode(); @endphp
                    <tr wire:key="mv-{{ $m->id }}">
                        <td class="small" style="white-space:nowrap">{{ $m->created_at->timezone($branch->timezone)->format('d/m/Y H:i') }}</td>
                        <td class="small"><strong>{{ $m->item->name }}</strong></td>
                        <td class="small">{{ $m->typeLabel() }}</td>
                        <td class="num {{ $m->quantity > 0 ? 'pos' : 'neg' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }} {{ $u }}</td>
                        <td class="num small">{{ rupiah($m->unit_cost, 2) }}</td>
                        <td class="num small">{{ rupiah($m->total_cost) }}</td>
                        <td class="num small">{{ qty($m->balance_after) }} {{ $u }}</td>
                        <td class="small">{{ $m->reason ?? '—' }}<div class="muted">{{ $m->user->name ?? 'Sistem' }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Belum ada pergerakan stok untuk filter ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())
            <div class="panel-body">{{ $movements->links('partials.pagination') }}</div>
        @endif
    </section>
</div>
