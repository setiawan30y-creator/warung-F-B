<div class="stack">
    <div class="page-head">
        <div>
            <h1>Cabang</h1>
            <p>{{ $count }} cabang terdaftar{{ $limit ? ', batas paket '.$limit : '' }}. Setiap transaksi nantinya tercatat di cabang asalnya.</p>
        </div>
        @if ($canManage)
            <button class="btn btn-primary" type="button" wire:click="create">
                <x-icon name="plus" :size="16"/> Tambah cabang
            </button>
        @endif
    </div>

    <section class="panel">
        <div class="panel-head">
            <input class="input" style="max-width:320px" type="search" placeholder="Cari nama, kode, atau kota"
                   wire:model.live.debounce.300ms="search" aria-label="Cari cabang">
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Cabang</th><th>Kontak</th><th>Lokasi</th><th>Pengguna</th><th>Status</th>@if($canManage)<th></th>@endif</tr>
                </thead>
                <tbody>
                @forelse ($branches as $b)
                    <tr wire:key="b-{{ $b->id }}">
                        <td><strong>{{ $b->name }}</strong><div class="muted small">{{ $b->code }}</div></td>
                        <td class="small">{{ $b->phone ?: '—' }}<div class="muted">{{ $b->email }}</div></td>
                        <td class="small">
                            {{ $b->city ?: '—' }}
                            @if ($b->hasCoordinates())
                                <div><a href="{{ $b->mapUrl() }}" target="_blank" rel="noopener">Lihat peta</a></div>
                            @else
                                <div class="muted">Koordinat belum diisi</div>
                            @endif
                        </td>
                        <td>{{ $b->users_count }}</td>
                        <td><span @class(['badge', 'ok' => $b->is_active])>{{ $b->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        @if ($canManage)
                            <td class="actions">
                                <button class="btn btn-ghost btn-sm" type="button" wire:click="edit({{ $b->id }})">Ubah</button>
                                <button class="btn btn-sm {{ $b->is_active ? 'btn-danger' : 'btn-ghost' }}" type="button"
                                        wire:click="toggleActive({{ $b->id }})"
                                        @if($b->is_active) wire:confirm="Nonaktifkan cabang {{ $b->name }}? Pengguna tidak bisa memilih cabang ini, tetapi datanya tetap tersimpan." @endif>
                                    {{ $b->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Tidak ada cabang yang cocok dengan pencarian.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showForm)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showForm', false)">
            <form class="modal" wire:submit="save" role="dialog" aria-modal="true" aria-labelledby="branch-form-title">
                <div class="panel-head">
                    <h2 id="branch-form-title">{{ $editingId ? 'Ubah cabang' : 'Tambah cabang' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showForm', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="f-name">Nama cabang</label>
                            <input id="f-name" class="input" wire:model="form.name" placeholder="Contoh: Cabang Pusat">
                            @error('form.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="f-code">Kode</label>
                            <input id="f-code" class="input" wire:model="form.code" placeholder="Contoh: PST" style="text-transform:uppercase">
                            <span class="hint">Dipakai pada nomor nota dan laporan.</span>
                            @error('form.code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="f-address">Alamat</label>
                            <input id="f-address" class="input" wire:model="form.address">
                        </div>
                        <div class="field">
                            <label for="f-city">Kota</label>
                            <input id="f-city" class="input" wire:model="form.city">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="f-phone">Telepon</label>
                            <input id="f-phone" class="input" wire:model="form.phone" inputmode="tel">
                            @error('form.phone') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="f-email">Email</label>
                            <input id="f-email" class="input" wire:model="form.email" type="email">
                            @error('form.email') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="field">
                            <label for="f-lat">Lintang</label>
                            <input id="f-lat" class="input" wire:model="form.latitude" inputmode="decimal" placeholder="-6.2000">
                            @error('form.latitude') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="f-lng">Bujur</label>
                            <input id="f-lng" class="input" wire:model="form.longitude" inputmode="decimal" placeholder="106.8166">
                            @error('form.longitude') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="f-tz">Zona waktu</label>
                            <select id="f-tz" class="select" wire:model="form.timezone">
                                <option value="Asia/Jakarta">WIB</option>
                                <option value="Asia/Makassar">WITA</option>
                                <option value="Asia/Jayapura">WIT</option>
                            </select>
                        </div>
                    </div>
                    <div x-data>
                        <button type="button" class="btn btn-ghost btn-sm"
                                x-on:click="navigator.geolocation?.getCurrentPosition(p => { $wire.set('form.latitude', p.coords.latitude.toFixed(7)); $wire.set('form.longitude', p.coords.longitude.toFixed(7)); }, () => alert('Lokasi perangkat tidak bisa dibaca. Isi koordinat secara manual.'))">
                            <x-icon name="pin" :size="16"/> Pakai lokasi perangkat ini
                        </button>
                        <p class="hint small muted" style="margin-top:6px">Lokasi hanya dibaca sekali saat tombol ditekan, untuk mengisi koordinat cabang.</p>
                    </div>

                    <hr class="div" style="margin:2px 0">
                    <h3>Jam operasional</h3>
                    <div class="stack" style="gap:8px">
                        @foreach (\App\Models\Branch::DAYS as $d => $label)
                            <div class="hours" wire:key="h-{{ $d }}">
                                <span class="small" style="font-weight:600">{{ $label }}</span>
                                <input class="input" type="time" wire:model="form.opening_hours.{{ $d }}.open" aria-label="Buka {{ $label }}" @disabled($form['opening_hours'][$d]['closed'] ?? false)>
                                <input class="input" type="time" wire:model="form.opening_hours.{{ $d }}.close" aria-label="Tutup {{ $label }}" @disabled($form['opening_hours'][$d]['closed'] ?? false)>
                                <label class="check small"><input type="checkbox" wire:model.live="form.opening_hours.{{ $d }}.closed"> Libur</label>
                            </div>
                        @endforeach
                    </div>

                    <label class="check"><input type="checkbox" wire:model="form.is_active"> Cabang aktif</label>

                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">{{ $editingId ? 'Simpan perubahan' : 'Tambah cabang' }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
