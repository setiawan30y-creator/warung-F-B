<div class="stack">
    <div class="page-head">
        <div>
            <h1>Log audit</h1>
            <p>Catatan setiap perubahan penting: siapa, kapan, dari mana, dan nilai sebelum serta sesudahnya. Log ini tidak dapat diubah atau dihapus.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-body row" style="gap:12px;align-items:flex-end">
            <div class="field" style="min-width:170px">
                <label for="a-event">Jenis</label>
                <select id="a-event" class="select" wire:model.live="event">
                    <option value="">Semua</option>
                    @foreach ($events as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="field" style="min-width:170px">
                <label for="a-user">Pengguna</label>
                <select id="a-user" class="select" wire:model.live="userId">
                    <option value="">Semua</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label for="a-from">Dari</label><input id="a-from" class="input" type="date" wire:model.live="from"></div>
            <div class="field"><label for="a-to">Sampai</label><input id="a-to" class="input" type="date" wire:model.live="to"></div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Kejadian</th><th>Cabang</th><th>Alamat IP</th><th></th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="l-{{ $log->id }}">
                        <td class="small" style="white-space:nowrap">{{ $log->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}</td>
                        <td class="small">{{ $log->user->name ?? 'Sistem' }}</td>
                        <td class="small">{{ $log->eventLabel() }}@if($log->description && strcasecmp($log->description, $log->eventLabel()) !== 0)<div class="muted">{{ $log->description }}</div>@endif</td>
                        <td class="small">{{ $log->branch->name ?? '—' }}</td>
                        <td class="small muted">{{ $log->ip_address ?? '—' }}</td>
                        <td class="actions">
                            @if ($log->old_values || $log->new_values)
                                <button class="btn btn-ghost btn-sm" type="button" wire:click="show({{ $log->id }})">Rincian</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Belum ada catatan untuk filter ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="panel-body">{{ $logs->links('partials.pagination') }}</div>
        @endif
    </section>

    @if ($detail)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('detailId', null)">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title">
                <div class="panel-head">
                    <div>
                        <h2 id="audit-detail-title">{{ $detail->eventLabel() }}</h2>
                        <p>{{ $detail->user->name ?? 'Sistem' }}, {{ $detail->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}</p>
                    </div>
                    <button class="icon-btn" type="button" wire:click="$set('detailId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Data</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
                        <tbody>
                        @php $keys = collect(array_keys(($detail->old_values ?? []) + ($detail->new_values ?? []))); @endphp
                        @foreach ($keys as $k)
                            @php
                                $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (string) ($v ?? '—'));
                            @endphp
                            <tr>
                                <td class="small"><strong>{{ $k }}</strong></td>
                                <td class="small muted" style="word-break:break-word">{{ array_key_exists($k, $detail->old_values ?? []) ? $fmt($detail->old_values[$k]) : '—' }}</td>
                                <td class="small" style="word-break:break-word">{{ array_key_exists($k, $detail->new_values ?? []) ? $fmt($detail->new_values[$k]) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
