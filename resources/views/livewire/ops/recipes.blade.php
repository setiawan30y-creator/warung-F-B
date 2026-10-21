<div class="stack">
    <div class="page-head">
        <div>
            <h1>Resep dan HPP</h1>
            <p>Saus, bumbu tabur, sambal, dan racikan lain dibuat sekali sebagai resep racikan, lalu dipakai di banyak menu. HPP dihitung untuk cabang {{ $branch->name ?? 'aktif' }}.</p>
        </div>
        @if ($tab === 'sub')
            <button class="btn btn-primary" type="button" wire:click="$set('showCreate', true)"><x-icon name="plus" :size="16"/> Racikan baru</button>
        @endif
    </div>

    <nav class="tabs" aria-label="Jenis resep">
        <button type="button" wire:click="$set('tab', 'menu')" aria-selected="{{ $tab === 'menu' ? 'true' : 'false' }}">Resep menu</button>
        <button type="button" wire:click="$set('tab', 'sub')" aria-selected="{{ $tab === 'sub' ? 'true' : 'false' }}">Racikan (sub-resep)</button>
    </nav>

    <section class="panel">
        <div class="filters">
            <div class="field" style="flex:1">
                <label for="r-search">Cari</label>
                <input id="r-search" class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $tab === 'menu' ? 'Nama menu' : 'Nama racikan' }}">
            </div>
        </div>

        @if ($tab === 'menu')
            @if ($rows->isEmpty())
                <div class="empty"><strong>Belum ada menu aktif</strong>Tambahkan menu di halaman Menu dan harga terlebih dahulu.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Menu</th><th>Resep</th><th class="num">Harga</th><th class="num">HPP</th><th class="num">Margin</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            <tr wire:key="rm-{{ $r['menu']->id }}">
                                <td><strong>{{ $r['menu']->name }}</strong><div class="muted small">{{ $r['menu']->category->name ?? 'Tanpa kategori' }}</div></td>
                                <td class="small">
                                    @if ($r['version'])
                                        {{ $r['version']->items->count() }} bahan, versi {{ $r['version']->version }}
                                    @else
                                        <span class="badge warn">Belum diatur</span>
                                    @endif
                                </td>
                                <td class="num">{{ rupiah($r['price']) }}</td>
                                <td class="num">{{ $r['hpp'] !== null ? rupiah($r['hpp']) : '—' }}</td>
                                <td class="num">
                                    @if ($r['margin'] !== null)
                                        <span @class(['neg' => $r['margin'] < 0.3])>{{ number_format($r['margin'] * 100, 1, ',', '.') }}%</span>
                                    @else — @endif
                                </td>
                                <td class="actions"><button class="btn btn-ghost btn-sm" type="button" wire:click="openMenu({{ $r['menu']->id }})">{{ $r['version'] ? 'Ubah resep' : 'Atur resep' }}</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            @if ($rows->isEmpty())
                <div class="empty"><strong>Belum ada racikan</strong>Buat racikan seperti saus, bumbu tabur, atau sambal. Hasilnya tercatat sebagai stok tersendiri dan bisa dipakai di banyak menu.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Racikan</th><th>Komposisi</th><th class="num">HPP per satuan</th><th class="num">Stok cabang</th><th>Dipakai di</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($rows as $r)
                            @php $unit = $r['recipe']->outputItem->unitCode(); @endphp
                            <tr wire:key="rs-{{ $r['recipe']->id }}">
                                <td><strong>{{ $r['recipe']->name }}</strong><div class="muted small">{{ $r['recipe']->outputItem->code }}</div></td>
                                <td class="small">
                                    @if ($r['version'])
                                        {{ $r['version']->items->count() }} bahan, hasil {{ qty($r['version']->yield_quantity) }} {{ $unit }}
                                    @else
                                        <span class="badge warn">Belum diatur</span>
                                    @endif
                                </td>
                                <td class="num">{{ $r['cost'] !== null ? rupiah($r['cost'], 2).' / '.$unit : '—' }}</td>
                                <td class="num">{{ qty($r['stock']) }} {{ $unit }}</td>
                                <td class="small">{{ $r['used_by'] }} resep</td>
                                <td class="actions"><a class="btn btn-ghost btn-sm" href="{{ route('ops.recipes.edit', $r['recipe']->id) }}" wire:navigate>Ubah</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </section>

    @if ($showCreate)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showCreate', false)">
            <form class="modal" wire:submit="createSub" role="dialog" aria-modal="true" aria-labelledby="sub-title">
                <div class="panel-head">
                    <h2 id="sub-title">Racikan baru</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showCreate', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <p class="muted small">Racikan menjadi bahan tersendiri dengan stok dan HPP sendiri. Komposisinya diatur di langkah berikutnya.</p>
                    <div class="grid-2">
                        <div class="field">
                            <label for="s-name">Nama racikan</label>
                            <input id="s-name" class="input" wire:model="sub.name" placeholder="Contoh: Saus keju">
                            @error('sub.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="s-code">Kode</label>
                            <input id="s-code" class="input" wire:model="sub.code" placeholder="SKJ" style="text-transform:uppercase">
                            @error('sub.code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="s-unit">Satuan hasil</label>
                            <select id="s-unit" class="select" wire:model="sub.base_unit_id">
                                <option value="">Pilih satuan</option>
                                @foreach ($baseUnits as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>@endforeach
                            </select>
                            <span class="hint">Umumnya gram untuk bumbu dan mililiter untuk saus cair.</span>
                            @error('sub.base_unit_id') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="s-cat">Kategori stok</label>
                            <input id="s-cat" class="input" wire:model="sub.category">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showCreate', false)">Batal</button>
                        <button class="btn btn-primary" type="submit">Buat dan atur komposisi</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
