<div class="stack">
    <div class="page-head">
        <div>
            <h1>Pengguna</h1>
            <p>{{ $count }} pengguna aktif{{ $limit ? ' dari batas '.$limit : '' }}. Akun login terpisah dari data karyawan, jadi tidak semua karyawan perlu akun.</p>
        </div>
        @if ($canManage)
            <button class="btn btn-primary" type="button" wire:click="create">
                <x-icon name="plus" :size="16"/> Tambah pengguna
            </button>
        @endif
    </div>

    <section class="panel">
        <div class="panel-head">
            <input class="input" style="max-width:320px" type="search" placeholder="Cari nama atau email"
                   wire:model.live.debounce.300ms="search" aria-label="Cari pengguna">
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nama</th><th>Peran</th><th>Cabang</th><th>Masuk terakhir</th><th>Status</th>@if($canManage)<th></th>@endif</tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td><strong>{{ $u->name }}</strong>@if($u->id === auth()->id()) <span class="muted small">(Anda)</span>@endif<div class="muted small">{{ $u->email }}</div></td>
                        <td class="small">{{ $u->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="small">{{ $u->canManageAllBranches() ? 'Semua cabang' : $u->branches()->withoutGlobalScopes()->pluck('name')->implode(', ') }}</td>
                        <td class="small muted">{{ $u->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                        <td><span @class(['badge', 'ok' => $u->is_active])>{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        @if ($canManage)
                            <td class="actions">
                                @if (! $u->isOwner() || auth()->user()->isOwner())
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="edit({{ $u->id }})">Ubah</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Tidak ada pengguna yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="panel-body">{{ $users->links('partials.pagination') }}</div>
        @endif
    </section>

    @if ($showForm)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showForm', false)">
            <form class="modal" wire:submit="save" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
                <div class="panel-head">
                    <h2 id="user-form-title">{{ $editingId ? 'Ubah pengguna' : 'Tambah pengguna' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showForm', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="u-name">Nama</label>
                            <input id="u-name" class="input" wire:model="form.name" placeholder="Nama lengkap">
                            @error('form.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="u-phone">Telepon</label>
                            <input id="u-phone" class="input" wire:model="form.phone" inputmode="tel">
                            @error('form.phone') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="u-email">Email login</label>
                            <input id="u-email" class="input" type="email" wire:model="form.email" placeholder="nama@usaha.com" autocomplete="off">
                            @error('form.email') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="u-pass">Kata sandi{{ $editingId ? ' baru' : '' }}</label>
                            <input id="u-pass" class="input" type="password" wire:model="form.password" autocomplete="new-password">
                            <span class="hint">{{ $editingId ? 'Kosongkan bila tidak diganti. ' : '' }}Minimal 8 karakter berisi huruf dan angka.</span>
                            @error('form.password') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <span class="label">Peran</span>
                        <div class="grid-2" style="gap:6px 16px">
                            @foreach ($roles as $r)
                                <label class="check small" wire:key="r-{{ $r->id }}">
                                    <input type="checkbox" value="{{ $r->id }}" wire:model.live="form.roles">
                                    <span><strong>{{ $r->name }}</strong><br><span class="muted">{{ $r->description }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        @error('form.roles') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <span class="label">Akses cabang</span>
                        <label class="check small"><input type="checkbox" wire:model.live="form.all_branches"> Semua cabang, termasuk cabang yang ditambahkan nanti</label>
                        @unless ($form['all_branches'])
                            <div class="grid-2" style="gap:6px 16px;margin-top:4px">
                                @foreach ($branches as $b)
                                    <label class="check small" wire:key="ub-{{ $b->id }}">
                                        <input type="checkbox" value="{{ $b->id }}" wire:model="form.branches">
                                        {{ $b->name }} <span class="muted">({{ $b->code }}){{ $b->is_active ? '' : ', nonaktif' }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endunless
                        @error('form.branches') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <label class="check"><input type="checkbox" wire:model="form.is_active"> Akun aktif</label>
                    @error('form.is_active') <span class="error">{{ $message }}</span> @enderror

                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">{{ $editingId ? 'Simpan perubahan' : 'Tambah pengguna' }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
