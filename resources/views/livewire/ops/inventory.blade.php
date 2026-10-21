<div class="stack">
    <div class="page-head">
        <div>
            <h1>Bahan dan stok</h1>
            <p>Stok cabang {{ $branch->name }}. Nilai persediaan {{ rupiah($totalValue) }}{{ $lowCount ? ', '.$lowCount.' bahan menipis atau habis' : '' }}.</p>
        </div>
        <div class="row">
            <a class="btn btn-ghost" href="{{ route('ops.ledger') }}" wire:navigate>Kartu stok</a>
            @if ($canManage)
                <button class="btn btn-primary" type="button" wire:click="create"><x-icon name="plus" :size="16"/> Tambah bahan</button>
            @endif
        </div>
    </div>

    <section class="panel">
        <div class="filters">
            <div class="field" style="flex:1;min-width:180px">
                <label for="i-search">Cari</label>
                <input id="i-search" class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="Nama atau kode bahan">
            </div>
            <div class="field">
                <label for="i-cat">Kategori</label>
                <select id="i-cat" class="select" wire:model.live="category">
                    <option value="">Semua</option>
                    @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="i-type">Jenis</label>
                <select id="i-type" class="select" wire:model.live="type">
                    <option value="">Semua</option>
                    @foreach (\App\Models\InventoryItem::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <label class="check small" style="padding-bottom:8px"><input type="checkbox" wire:model.live="lowOnly"> Hanya yang menipis</label>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">
                <strong>{{ $search || $category || $type || $lowOnly ? 'Tidak ada bahan yang cocok' : 'Belum ada bahan' }}</strong>
                @unless ($search || $category || $type || $lowOnly) Tambahkan bahan yang dibeli seperti kentang, minyak, atau kemasan. Racikan dibuat dari halaman Resep. @endunless
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Bahan</th><th>Jenis</th><th class="num">Stok</th><th class="num">Minimum</th><th class="num">Biaya rata-rata</th><th class="num">Nilai</th><th>Status</th>@if($canManage)<th></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php $i = $r['item']; $u = $i->unitCode(); @endphp
                        <tr wire:key="it-{{ $i->id }}" @if(! $i->is_active) style="opacity:.6" @endif>
                            <td><a href="{{ route('ops.ledger', ['item' => $i->id]) }}" wire:navigate style="color:inherit;text-decoration:none" title="Lihat kartu stok"><strong>{{ $i->name }}</strong></a><div class="muted small">{{ $i->code }}{{ $i->category ? ', '.$i->category : '' }}</div></td>
                            <td class="small" style="white-space:nowrap">{{ \App\Models\InventoryItem::TYPES[$i->type] }}</td>
                            <td class="num">{{ qty($r['qty']) }} {{ $u }}</td>
                            <td class="num small muted">{{ $i->min_stock > 0 ? qty($i->min_stock).' '.$u : '—' }}</td>
                            <td class="num small">{{ rupiah($r['unit_cost'], 2) }} / {{ $u }}</td>
                            <td class="num">{{ rupiah($r['value']) }}</td>
                            <td>
                                @if (! $i->is_active)<span class="badge">Nonaktif</span>
                                @elseif ($r['status'] === 'out')<span class="badge bad">Habis</span>
                                @elseif ($r['status'] === 'low')<span class="badge warn">Menipis</span>
                                @else<span class="badge ok">Cukup</span>@endif
                            </td>
                            @if ($canManage)
                                <td class="actions">
                                    @if (! isset($movedItemIds[$i->id]))
                                        <button class="btn btn-ghost btn-sm" type="button" wire:click="openAdjust({{ $i->id }})">Saldo awal</button>
                                    @elseif ($canApprove)
                                        <button class="btn btn-ghost btn-sm" type="button" wire:click="openAdjust({{ $i->id }})">Sesuaikan</button>
                                    @endif
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="edit({{ $i->id }})">Ubah</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($showForm)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showForm', false)">
            <form class="modal" wire:submit="save" role="dialog" aria-modal="true" aria-labelledby="item-title">
                <div class="panel-head">
                    <h2 id="item-title">{{ $editingId ? 'Ubah bahan' : 'Tambah bahan' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showForm', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-3">
                        <div class="field" style="grid-column:span 2">
                            <label for="it-name">Nama bahan</label>
                            <input id="it-name" class="input" wire:model="form.name" placeholder="Contoh: Kentang">
                            @error('form.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="it-code">Kode</label>
                            <input id="it-code" class="input" wire:model="form.code" placeholder="KTG" style="text-transform:uppercase">
                            @error('form.code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="field">
                            <label for="it-cat">Kategori</label>
                            <input id="it-cat" class="input" wire:model="form.category" list="item-cats" placeholder="Contoh: Sayur">
                            <datalist id="item-cats">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                        </div>
                        <div class="field">
                            <label for="it-unit">Satuan dasar</label>
                            <select id="it-unit" class="select" wire:model.live="form.base_unit_id">
                                <option value="">Pilih</option>
                                @foreach ($baseUnits as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>@endforeach
                            </select>
                            <span class="hint">Satuan terkecil yang dipakai di resep.</span>
                            @error('form.base_unit_id') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="it-min">Stok minimum</label>
                            <input id="it-min" class="input" type="number" step="any" min="0" wire:model="form.min_stock">
                            @error('form.min_stock') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    @php $baseCode = $baseUnits->firstWhere('id', (int) $form['base_unit_id'])?->code; @endphp
                    @unless ($editingId && \App\Models\InventoryItem::find($editingId)?->isProduced())
                        <div class="field">
                            <label for="it-cost">Harga acuan per {{ $baseCode ?? 'satuan dasar' }} (Rp)</label>
                            <input id="it-cost" class="input" type="number" step="any" min="0" wire:model="form.last_cost" style="max-width:240px">
                            <span class="hint">Dipakai menghitung HPP sebelum ada stok. Diperbarui otomatis setiap barang diterima dari pembelian.</span>
                            @error('form.last_cost') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endunless

                    <div class="field">
                        <span class="label">Isi kemasan</span>
                        <span class="hint">Contoh: 1 karung = 25000 g, 1 tray = 30 butir. Satuan standar seperti kg dan liter sudah dikenali otomatis.</span>
                        @foreach ($conversions as $ci => $c)
                            <div class="row" style="flex-wrap:nowrap" wire:key="cv-{{ $ci }}">
                                <span class="small">1</span>
                                <select class="select" wire:model="conversions.{{ $ci }}.unit" aria-label="Satuan kemasan" style="max-width:160px">
                                    <option value="">Pilih</option>
                                    @foreach ($allUnits as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                </select>
                                <span class="small">=</span>
                                <input class="input" type="number" step="any" min="0" wire:model="conversions.{{ $ci }}.factor" aria-label="Isi kemasan" style="max-width:140px">
                                <span class="small">{{ $baseCode ?? '' }}</span>
                                <button class="icon-btn" type="button" wire:click="removeConversion({{ $ci }})" aria-label="Hapus"><x-icon name="x" :size="16"/></button>
                            </div>
                            @error('conversions.'.$ci.'.unit') <span class="error">{{ $message }}</span> @enderror
                            @error('conversions.'.$ci.'.factor') <span class="error">{{ $message }}</span> @enderror
                        @endforeach
                        <div><button class="btn btn-ghost btn-sm" type="button" wire:click="addConversion"><x-icon name="plus" :size="16"/> Tambah kemasan</button></div>
                    </div>

                    <label class="check"><input type="checkbox" wire:model="form.is_active"> Bahan aktif</label>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit">{{ $editingId ? 'Simpan perubahan' : 'Tambah bahan' }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($adjustItem)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('adjustId', null)">
            <form class="modal" wire:submit="saveAdjust" role="dialog" aria-modal="true" aria-labelledby="adj-title">
                <div class="panel-head">
                    <h2 id="adj-title">{{ $adjustIsOpening ? 'Saldo awal' : 'Penyesuaian stok' }}: {{ $adjustItem->name }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('adjustId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <p class="muted small">
                        {{ $adjustIsOpening
                            ? 'Isi jumlah stok fisik saat mulai memakai aplikasi beserta harga belinya.'
                            : 'Penyesuaian tercatat di kartu stok dan log audit. Untuk selisih hasil hitung fisik, gunakan stok opname.' }}
                    </p>
                    @unless ($adjustIsOpening)
                        <div class="seg" role="group" aria-label="Arah penyesuaian">
                            <button type="button" wire:click="$set('adjust.direction','in')" aria-pressed="{{ $adjust['direction'] === 'in' ? 'true' : 'false' }}">Tambah</button>
                            <button type="button" wire:click="$set('adjust.direction','out')" aria-pressed="{{ $adjust['direction'] === 'out' ? 'true' : 'false' }}">Kurangi</button>
                        </div>
                    @endunless
                    <div class="grid-3">
                        <div class="field">
                            <label for="adj-q">Jumlah</label>
                            <input id="adj-q" class="input" type="number" step="any" min="0" wire:model="adjust.quantity">
                        </div>
                        <div class="field">
                            <label for="adj-u">Satuan</label>
                            <select id="adj-u" class="select" wire:model.live="adjust.unit">
                                @foreach ($adjustUnits as $o)<option value="{{ $o['id'] }}">{{ $o['code'] }}</option>@endforeach
                            </select>
                        </div>
                        @if ($adjust['direction'] === 'in')
                            <div class="field">
                                <label for="adj-c">Harga per satuan (Rp)</label>
                                <input id="adj-c" class="input" type="number" step="any" min="0" wire:model="adjust.unit_cost">
                                @error('adjust.unit_cost') <span class="error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    @error('adjust.quantity') <span class="error">{{ $message }}</span> @enderror
                    <div class="field">
                        <label for="adj-r">Alasan</label>
                        <input id="adj-r" class="input" wire:model="adjust.reason" placeholder="Wajib diisi">
                        @error('adjust.reason') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('adjustId', null)">Batal</button>
                        <button class="btn btn-primary" type="submit">{{ $adjustIsOpening ? 'Catat saldo awal' : 'Catat penyesuaian' }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
