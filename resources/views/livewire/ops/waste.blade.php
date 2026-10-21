<div class="stack">
    <div class="page-head">
        <div>
            <h1>Susut dan rusak</h1>
            <p>Catat bahan yang rusak, basi, kedaluwarsa, tumpah, atau gagal diproduksi di {{ $branch->name }}. Susut di atas {{ rupiah($threshold) }} perlu persetujuan pengguna lain yang berwenang.</p>
        </div>
    </div>

    <div class="split">
        <div class="stack">
            @if ($canManage)
                <form class="panel" wire:submit="record">
                    <div class="panel-head"><h2>Catat susut</h2></div>
                    <div class="panel-body stack">
                        <div class="grid-3">
                            <div class="field" style="grid-column:span 2">
                                <label for="w-item">Bahan</label>
                                <select id="w-item" class="select" wire:model.live="form.item">
                                    <option value="">Pilih bahan</option>
                                    @foreach ($items as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach
                                </select>
                                @error('form.item') <span class="error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="w-reason">Alasan</label>
                                <select id="w-reason" class="select" wire:model.live="form.reason">
                                    <option value="">Pilih</option>
                                    @foreach (\App\Models\WasteRecord::REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                                @error('form.reason') <span class="error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid-3">
                            <div class="field">
                                <label for="w-qty">Jumlah</label>
                                <input id="w-qty" class="input" type="number" step="any" min="0" wire:model="form.quantity">
                            </div>
                            <div class="field">
                                <label for="w-unit">Satuan</label>
                                <select id="w-unit" class="select" wire:model="form.unit">
                                    <option value="">—</option>
                                    @foreach ($unitOptions as $o)<option value="{{ $o['id'] }}">{{ $o['code'] }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="w-notes">Keterangan</label>
                                <input id="w-notes" class="input" wire:model="form.notes" placeholder="{{ $form['reason'] === 'lainnya' ? 'Wajib diisi' : 'Opsional' }}">
                            </div>
                        </div>
                        @error('form.quantity') <span class="error">{{ $message }}</span> @enderror
                        <div class="form-actions"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Catat susut</button></div>
                    </div>
                </form>
            @endif

            @if ($pending->isNotEmpty())
                <section class="panel">
                    <div class="panel-head"><div><h2>Menunggu persetujuan</h2><p>Stok belum dikurangi sampai disetujui.</p></div></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Bahan</th><th>Alasan</th><th class="num">Nilai</th><th>Dicatat</th>@if($canApprove)<th></th>@endif</tr></thead>
                            <tbody>
                            @foreach ($pending as $p)
                                <tr wire:key="wp-{{ $p->id }}">
                                    <td><strong>{{ $p->item->name }}</strong><div class="muted small">{{ qty($p->quantity) }} {{ $p->unit->code }}</div></td>
                                    <td class="small">{{ $p->reasonLabel() }}@if($p->notes)<div class="muted">{{ $p->notes }}</div>@endif</td>
                                    <td class="num">{{ rupiah($p->estimated_value) }}</td>
                                    <td class="small">{{ $p->creator->name ?? '—' }}<div class="muted">{{ $p->created_at->diffForHumans() }}</div></td>
                                    @if ($canApprove)
                                        <td class="actions">
                                            @if ($p->created_by !== auth()->id())
                                                <button class="btn btn-primary btn-sm" type="button" wire:click="approve({{ $p->id }})" wire:confirm="Setujui susut {{ $p->item->name }} senilai {{ rupiah($p->estimated_value) }}? Stok akan dikurangi.">Setujui</button>
                                                <button class="btn btn-danger btn-sm" type="button" wire:click="$set('rejectId', {{ $p->id }})">Tolak</button>
                                            @else
                                                <span class="muted small">Perlu disetujui orang lain</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="panel">
                <div class="panel-head">
                    <h2>Riwayat {{ $periodLabel }}</h2>
                    <input class="input" type="month" wire:model.live="month" style="max-width:170px" aria-label="Pilih bulan">
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Tanggal</th><th>Bahan</th><th>Alasan</th><th class="num">Nilai</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($history as $h)
                            <tr wire:key="wh-{{ $h->id }}">
                                <td class="small">{{ $h->created_at->timezone($branch->timezone)->format('d/m H:i') }}</td>
                                <td><strong>{{ $h->item->name }}</strong><div class="muted small">{{ qty($h->quantity) }} {{ $h->unit->code }}, oleh {{ $h->creator->name ?? '—' }}</div></td>
                                <td class="small">{{ $h->reasonLabel() }}@if($h->notes)<div class="muted">{{ $h->notes }}</div>@endif</td>
                                <td class="num">{{ rupiah($h->estimated_value) }}</td>
                                <td class="small">
                                    <span @class(['badge', 'ok' => $h->status === 'approved', 'bad' => $h->status === 'rejected'])>{{ \App\Models\WasteRecord::STATUSES[$h->status] }}</span>
                                    @if ($h->decision_note)<div class="muted">{{ $h->decision_note }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">Tidak ada susut pada bulan ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($history->hasPages())<div class="panel-body">{{ $history->links('partials.pagination') }}</div>@endif
            </section>
        </div>

        <aside class="stack">
            <section class="panel">
                <div class="panel-head"><h2>Kerugian {{ $periodLabel }}</h2></div>
                <div class="panel-body">
                    <div class="big-number">{{ rupiah($periodTotal) }}</div>
                    <p class="muted small" style="margin:2px 0 12px">Susut yang sudah disetujui di {{ $branch->name }}.</p>
                    @foreach ($byReason as $r)
                        <div class="sum-row"><span>{{ \App\Models\WasteRecord::REASONS[$r->reason] ?? $r->reason }} <span class="muted small">({{ $r->n }}×)</span></span><strong>{{ rupiah($r->total) }}</strong></div>
                    @endforeach
                </div>
            </section>
            @if ($byItem->isNotEmpty())
                <section class="panel">
                    <div class="panel-head"><h2>Bahan paling sering terbuang</h2></div>
                    <div class="panel-body">
                        @foreach ($byItem as $r)
                            <div class="sum-row"><span>{{ $r->item->name ?? '—' }}</span><strong>{{ rupiah($r->total) }}</strong></div>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @if ($rejectId)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('rejectId', null)">
            <form class="modal" wire:submit="reject" role="dialog" aria-modal="true">
                <div class="panel-head"><h2>Tolak susut</h2><button class="icon-btn" type="button" wire:click="$set('rejectId', null)" aria-label="Tutup"><x-icon name="x"/></button></div>
                <div class="panel-body stack">
                    <div class="field">
                        <label for="rj-note">Alasan penolakan</label>
                        <input id="rj-note" class="input" wire:model="rejectNote" placeholder="Contoh: barang masih layak dipakai">
                        @error('rejectNote') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('rejectId', null)">Batal</button>
                        <button class="btn btn-danger" type="submit">Tolak susut</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
