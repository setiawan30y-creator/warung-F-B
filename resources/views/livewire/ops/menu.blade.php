<div class="stack">
    <div class="page-head">
        <div>
            <h1>Menu dan harga</h1>
            <p>Harga dasar berlaku di semua cabang, kecuali cabang yang punya harga sendiri. Status dan HPP di tabel mengikuti cabang aktif{{ $activeBranch ? ': '.$activeBranch->name : '' }}.</p>
        </div>
        <div class="row">
            <button class="btn btn-ghost" type="button" wire:click="$set('showCategories', true)">Kategori</button>
            <button class="btn btn-primary" type="button" wire:click="create"><x-icon name="plus" :size="16"/> Tambah menu</button>
        </div>
    </div>

    <section class="panel">
        <div class="filters">
            <div class="field" style="flex:1;min-width:200px">
                <label for="m-search">Cari</label>
                <input id="m-search" class="input" type="search" placeholder="Nama atau kode menu" wire:model.live.debounce.300ms="search">
            </div>
            <div class="field">
                <label for="m-cat">Kategori</label>
                <select id="m-cat" class="select" wire:model.live="category">
                    <option value="">Semua</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    <option value="none">Tanpa kategori</option>
                </select>
            </div>
        </div>
        @if ($rows->isEmpty())
            <div class="empty">
                <strong>Belum ada menu{{ $search || $category ? ' yang cocok' : '' }}</strong>
                @unless ($search || $category) Tambahkan menu pertama, lalu atur resepnya agar HPP terhitung otomatis. @endunless
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>Menu</th><th>Kategori</th><th class="num">Harga</th>
                        @if ($hasInventory)<th class="num">HPP</th><th class="num">Margin</th>@endif
                        <th>Status di cabang</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php $m = $r['menu']; $b = $r['branch']; @endphp
                        <tr wire:key="m-{{ $m->id }}">
                            <td>
                                <div class="row" style="flex-wrap:nowrap">
                                    @if ($m->photoUrl())
                                        <img class="thumb" src="{{ $m->photoUrl() }}" alt="" loading="lazy">
                                    @else
                                        <span class="thumb"><x-icon name="bowl" :size="20"/></span>
                                    @endif
                                    <div><strong>{{ $m->name }}</strong><div class="muted small">{{ $m->code }}</div></div>
                                </div>
                            </td>
                            <td class="small">{{ $m->category->name ?? '—' }}</td>
                            <td class="num">{{ rupiah($b['price']) }}@if($b['is_override'])<div class="muted small">harga cabang</div>@endif</td>
                            @if ($hasInventory)
                                <td class="num">{{ $r['hpp'] !== null ? rupiah($r['hpp']) : '—' }}</td>
                                <td class="num">
                                    @if ($r['margin'] !== null)
                                        <span @class(['neg' => $r['margin'] < 0.3])>{{ number_format($r['margin'] * 100, 1, ',', '.') }}%</span>
                                    @else
                                        <span class="muted small">Belum ada resep</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if (! $m->is_active)
                                    <span class="badge">Nonaktif</span>
                                @elseif (! $b['is_available'])
                                    <span class="badge">Tidak dijual</span>
                                @elseif ($b['is_sold_out'])
                                    <span class="badge bad">Habis</span>
                                @else
                                    <span class="badge ok">Tersedia</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($m->is_active && $b['is_available'] && $activeBranch)
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="toggleSoldOut({{ $m->id }})">{{ $b['is_sold_out'] ? 'Tersedia lagi' : 'Tandai habis' }}</button>
                                @endif
                                @if ($hasInventory && $canRecipes)
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="openRecipe({{ $m->id }})">Resep</button>
                                @endif
                                <button class="btn btn-ghost btn-sm" type="button" wire:click="edit({{ $m->id }})">Ubah</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($showForm)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showForm', false)">
            <form class="modal wide" wire:submit="save" role="dialog" aria-modal="true" aria-labelledby="menu-form-title">
                <div class="panel-head">
                    <h2 id="menu-form-title">{{ $editingId ? 'Ubah menu' : 'Tambah menu' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showForm', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="row" style="align-items:flex-start;gap:16px">
                        <div class="logo-box" style="width:96px;height:96px">
                            @if ($photo && ! $errors->has('photo'))
                                <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto">
                            @elseif ($editingId && ($cur = \App\Models\Menu::find($editingId)?->photoUrl()))
                                <img src="{{ $cur }}" alt="Foto menu">
                            @else
                                <span class="muted small">Foto</span>
                            @endif
                        </div>
                        <div class="field" style="flex:1;min-width:200px">
                            <label for="mf-photo">Foto menu</label>
                            <input id="mf-photo" class="input" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp">
                            <span class="hint">PNG, JPG, atau WebP, maksimal 3 MB. Tampil di QR menu dan aplikasi pelanggan.</span>
                            @error('photo') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="field" style="grid-column:span 2">
                            <label for="mf-name">Nama menu</label>
                            <input id="mf-name" class="input" wire:model="form.name" placeholder="Contoh: Kentang crispy reguler">
                            @error('form.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="mf-code">Kode</label>
                            <input id="mf-code" class="input" wire:model="form.code" placeholder="KC-R" style="text-transform:uppercase">
                            @error('form.code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="mf-cat">Kategori</label>
                            <select id="mf-cat" class="select" wire:model="form.menu_category_id">
                                <option value="">Tanpa kategori</option>
                                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="mf-price">Harga dasar (Rp)</label>
                            <input id="mf-price" class="input" type="number" min="0" step="100" wire:model="form.price" inputmode="numeric">
                            @error('form.price') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="mf-desc">Deskripsi</label>
                        <textarea id="mf-desc" class="textarea" wire:model="form.description" placeholder="Isi, rasa, atau ukuran porsi"></textarea>
                    </div>
                    <label class="check"><input type="checkbox" wire:model="form.is_active"> Menu aktif</label>

                    <div>
                        <h3>Pengaturan per cabang</h3>
                        <p class="muted small" style="margin-top:2px">Kosongkan harga cabang untuk memakai harga dasar.</p>
                    </div>
                    <div class="table-wrap" style="border:1px solid var(--c-border);border-radius:8px">
                        <table class="table">
                            <thead><tr><th>Cabang</th><th>Harga cabang (Rp)</th><th>Dijual</th><th>Habis</th></tr></thead>
                            <tbody>
                            @foreach ($branches as $br)
                                <tr wire:key="bf-{{ $br->id }}">
                                    <td><strong>{{ $br->name }}</strong>@unless($br->is_active)<div class="muted small">nonaktif</div>@endunless</td>
                                    <td style="min-width:140px">
                                        <input class="input" type="number" min="0" step="100" wire:model="branchForm.{{ $br->id }}.price" placeholder="Ikut harga dasar" aria-label="Harga di {{ $br->name }}">
                                        @error('branchForm.'.$br->id.'.price') <span class="error">{{ $message }}</span> @enderror
                                    </td>
                                    <td><label class="check"><input type="checkbox" wire:model="branchForm.{{ $br->id }}.is_available"><span class="sr-only">Dijual di {{ $br->name }}</span></label></td>
                                    <td><label class="check"><input type="checkbox" wire:model="branchForm.{{ $br->id }}.is_sold_out"><span class="sr-only">Habis di {{ $br->name }}</span></label></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions">
                        @if ($editingId)
                            <button class="btn btn-ghost" type="button" style="margin-right:auto" wire:click="$set('historyId', {{ $editingId }})">Riwayat harga</button>
                        @endif
                        <button class="btn btn-ghost" type="button" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">{{ $editingId ? 'Simpan perubahan' : 'Tambah menu' }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($history !== null)
        <div class="modal-backdrop" style="z-index:55" wire:keydown.escape.window="$set('historyId', null)">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="panel-head">
                    <h2>Riwayat harga {{ $historyMenu?->name }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('historyId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Waktu</th><th>Berlaku untuk</th><th class="num">Lama</th><th class="num">Baru</th><th>Oleh</th></tr></thead>
                        <tbody>
                        @forelse ($history as $h)
                            <tr>
                                <td class="small">{{ $h->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                                <td class="small">{{ $h->branch->name ?? 'Harga dasar' }}</td>
                                <td class="num small">{{ $h->old_price !== null ? rupiah($h->old_price) : '—' }}</td>
                                <td class="num small">{{ $h->new_price !== null ? rupiah($h->new_price) : 'Ikut harga dasar' }}</td>
                                <td class="small">{{ $h->user->name ?? 'Sistem' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">Belum ada perubahan harga.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($showCategories)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showCategories', false)">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cat-title">
                <div class="panel-head">
                    <h2 id="cat-title">Kategori menu</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showCategories', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <form class="row" wire:submit="addCategory" style="flex-wrap:nowrap">
                        <input class="input" wire:model="newCategory" placeholder="Contoh: Minuman dingin" aria-label="Nama kategori baru">
                        <button class="btn btn-primary" type="submit">Tambah</button>
                    </form>
                    @error('newCategory') <span class="error">{{ $message }}</span> @enderror
                    <ul class="roadmap" style="list-style:none;margin:0;padding:0">
                        @forelse ($categories as $c)
                            <li wire:key="cat-{{ $c->id }}">
                                <span style="flex:1"><strong>{{ $c->name }}</strong> <span class="muted small">{{ $c->menus_count }} menu</span></span>
                                <button class="btn btn-ghost btn-sm" type="button" wire:click="moveCategory({{ $c->id }}, -1)" @disabled($loop->first) aria-label="Naikkan {{ $c->name }}">Naik</button>
                                <button class="btn btn-ghost btn-sm" type="button" wire:click="moveCategory({{ $c->id }}, 1)" @disabled($loop->last) aria-label="Turunkan {{ $c->name }}">Turun</button>
                                <button class="btn btn-danger btn-sm" type="button" wire:click="deleteCategory({{ $c->id }})" wire:confirm="Hapus kategori {{ $c->name }}?">Hapus</button>
                            </li>
                        @empty
                            <li class="muted">Belum ada kategori.</li>
                        @endforelse
                    </ul>
                    <p class="muted small">Urutan kategori dipakai di kasir dan QR menu.</p>
                </div>
            </div>
        </div>
    @endif
</div>
