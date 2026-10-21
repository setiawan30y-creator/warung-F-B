<div class="stack">
    <div class="page-head">
        <div>
            <h1>Peran dan izin</h1>
            <p>Izin diperiksa di server untuk setiap tindakan. Izin modul yang belum dibangun bisa disiapkan dari sekarang.</p>
        </div>
        <button class="btn btn-primary" type="button" wire:click="startCreate">
            <x-icon name="plus" style="width:16px;height:16px"/> Peran baru
        </button>
    </div>

    <div class="split" style="grid-template-columns:minmax(0,.8fr) minmax(0,2fr)">
        <section class="panel">
            <div class="panel-head"><h2>Daftar peran</h2></div>
            <nav class="nav" style="padding:8px;margin:0">
                @foreach ($roles as $r)
                    <a href="#" wire:click.prevent="select({{ $r->id }})" wire:key="role-{{ $r->id }}"
                       @if($selectedId === $r->id && ! $creating) aria-current="page" @endif>
                        <span style="flex:1">{{ $r->name }}</span>
                        <span class="muted small">{{ $r->users_count }} orang</span>
                    </a>
                @endforeach
            </nav>
        </section>

        <form class="panel" wire:submit="save">
            <div class="panel-head">
                <div>
                    <h2>{{ $creating ? 'Peran baru' : ($selected->name ?? 'Pilih peran') }}</h2>
                    @if ($selected?->is_system)<p>Peran bawaan</p>@endif
                </div>
                @if ($selected && ! $selected->is_system && ! $creating)
                    <button class="btn btn-danger btn-sm" type="button" wire:click="delete"
                            wire:confirm="Hapus peran {{ $selected->name }}?">Hapus peran</button>
                @endif
            </div>
            <div class="panel-body stack">
                @if ($locked)
                    <div class="alert warn">Peran Pemilik selalu memiliki semua izin dan akses ke semua cabang, sehingga tidak dapat diubah.</div>
                @endif
                <div class="grid-2">
                    <div class="field">
                        <label for="r-name">Nama peran</label>
                        <input id="r-name" class="input" wire:model="name" @disabled($locked) placeholder="Contoh: Supervisor shift">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="r-desc">Keterangan</label>
                        <input id="r-desc" class="input" wire:model="description" @disabled($locked)>
                    </div>
                </div>

                <div class="perm-grid">
                    @foreach ($groups as $group => $items)
                        <div class="perm-group">{{ $group }}</div>
                        @foreach ($items as $key => $label)
                            <label class="check small" wire:key="p-{{ $key }}">
                                <input type="checkbox" value="{{ $key }}" wire:model="permissions" @disabled($locked) @checked($locked)>
                                {{ $label }}
                            </label>
                        @endforeach
                    @endforeach
                </div>

                @error('permissions') <span class="error">{{ $message }}</span> @enderror
                @unless ($locked)
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">{{ $creating ? 'Buat peran' : 'Simpan peran' }}</button>
                    </div>
                @endunless
            </div>
        </form>
    </div>
</div>
