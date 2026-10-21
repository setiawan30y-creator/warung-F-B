<div class="stack">
    <div class="page-head">
        <div>
            <h1>Stok opname</h1>
            <p>Hitung stok fisik di {{ $branch->name }} lalu bandingkan dengan stok sistem. Selisih dihitung terhadap stok saat diposting, sehingga transaksi selama penghitungan tetap tercatat benar.</p>
        </div>
        <button class="btn btn-primary" type="button" wire:click="$set('showCreate', true)"><x-icon name="plus" :size="16"/> Mulai opname</button>
    </div>

    <section class="panel">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Cakupan</th><th>Progres</th><th class="num">Selisih</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($counts as $c)
                    <tr wire:key="sc-{{ $c->id }}">
                        <td class="small"><strong>{{ $c->number }}</strong><div class="muted">{{ $c->created_at->timezone($branch->timezone)->format('d/m/Y H:i') }}, {{ $c->creator->name ?? '—' }}</div></td>
                        <td class="small">{{ $c->category ?? 'Semua bahan' }}</td>
                        <td class="small">{{ $c->counted_count }} dari {{ $c->lines_count }} dihitung</td>
                        <td class="num">{{ $c->variance_value !== null ? rupiah($c->variance_value) : '—' }}</td>
                        <td><span @class(['badge', 'ok' => $c->status === 'posted', 'info' => $c->status === 'draft'])>{{ $c->status === 'posted' ? 'Diposting' : 'Sedang dihitung' }}</span></td>
                        <td class="actions"><button class="btn btn-ghost btn-sm" type="button" wire:click="open({{ $c->id }})">{{ $c->status === 'draft' ? 'Lanjutkan' : 'Lihat' }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty"><strong>Belum ada opname</strong>Lakukan opname rutin, misalnya mingguan, untuk menangkap selisih stok lebih awal.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($counts->hasPages())<div class="panel-body">{{ $counts->links('partials.pagination') }}</div>@endif
    </section>

    @if ($showCreate)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showCreate', false)">
            <form class="modal" wire:submit="start" role="dialog" aria-modal="true">
                <div class="panel-head"><h2>Mulai opname</h2><button class="icon-btn" type="button" wire:click="$set('showCreate', false)" aria-label="Tutup"><x-icon name="x"/></button></div>
                <div class="panel-body stack">
                    <div class="field">
                        <label for="sc-cat">Kategori yang dihitung</label>
                        <select id="sc-cat" class="select" wire:model="category">
                            <option value="">Semua bahan</option>
                            @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                        </select>
                        @error('category') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field"><label for="sc-notes">Catatan</label><input id="sc-notes" class="input" wire:model="notes" placeholder="Contoh: opname akhir pekan"></div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showCreate', false)">Batal</button>
                        <button class="btn btn-primary" type="submit">Mulai menghitung</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($count)
        @php $draft = $count->status === 'draft'; @endphp
        <div class="modal-backdrop" wire:keydown.escape.window="$set('countId', null)">
            <form class="modal wide" wire:submit="saveCounts" role="dialog" aria-modal="true" aria-labelledby="sc-title">
                <div class="panel-head">
                    <div>
                        <h2 id="sc-title">{{ $count->number }}</h2>
                        <p>{{ $draft ? 'Isi jumlah fisik. Baris yang dikosongkan tidak ikut disesuaikan.' : 'Diposting '.$count->posted_at?->timezone($branch->timezone)->format('d/m/Y H:i').'.' }}</p>
                    </div>
                    <button class="icon-btn" type="button" wire:click="$set('countId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Bahan</th><th class="num">Stok sistem saat mulai</th><th>Jumlah fisik</th>@unless($draft)<th class="num">Stok saat posting</th><th class="num">Selisih</th><th class="num">Nilai</th>@endunless</tr></thead>
                        <tbody>
                        @foreach ($count->lines as $l)
                            @php $u = $l->item->unitCode(); @endphp
                            <tr wire:key="scl-{{ $l->id }}">
                                <td><strong>{{ $l->item->name }}</strong><div class="muted small">{{ $l->item->code }}</div></td>
                                <td class="num">{{ qty($l->system_quantity) }} {{ $u }}</td>
                                <td style="min-width:150px">
                                    @if ($draft)
                                        <div class="row" style="flex-wrap:nowrap"><input class="input" type="number" step="any" min="0" wire:model="counted.{{ $l->id }}" aria-label="Jumlah fisik {{ $l->item->name }}"><span class="small">{{ $u }}</span></div>
                                    @else
                                        {{ $l->counted_quantity !== null ? qty($l->counted_quantity).' '.$u : 'Tidak dihitung' }}
                                    @endif
                                </td>
                                @unless ($draft)
                                    <td class="num">{{ $l->posted_system_quantity !== null ? qty($l->posted_system_quantity).' '.$u : '—' }}</td>
                                    <td class="num {{ ($l->variance ?? 0) < 0 ? 'neg' : (($l->variance ?? 0) > 0 ? 'pos' : '') }}">{{ $l->variance !== null ? ($l->variance > 0 ? '+' : '').qty($l->variance).' '.$u : '—' }}</td>
                                    <td class="num">{{ $l->variance_value !== null ? rupiah($l->variance_value) : '—' }}</td>
                                @endunless
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-body">
                    @if ($draft)
                        <div class="form-actions">
                            <button class="btn btn-ghost" type="submit">Simpan hitungan</button>
                            @if ($canPost)
                                <button class="btn btn-primary" type="button" wire:click="post" wire:confirm="Posting opname? Stok sistem akan disamakan dengan jumlah fisik dan selisihnya dicatat. Tindakan ini tidak dapat dibatalkan.">Posting opname</button>
                            @else
                                <span class="muted small" style="align-self:center">Posting dilakukan oleh pengguna dengan izin persetujuan.</span>
                            @endif
                        </div>
                    @else
                        <div class="sum-row"><span>Total nilai selisih</span><strong @class(['neg' => $count->variance_value < 0])>{{ rupiah($count->variance_value) }}</strong></div>
                    @endif
                </div>
            </form>
        </div>
    @endif
</div>
