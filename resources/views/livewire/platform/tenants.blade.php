<div class="stack">
    <div class="page-head">
        <div>
            <h1>Tenant</h1>
            <p>Setiap tenant adalah satu usaha dengan data, pengguna, dan cabang yang terpisah. Menonaktifkan tenant tidak menghapus datanya.</p>
        </div>
        <button class="btn btn-primary" type="button" wire:click="create"><x-icon name="plus" style="width:16px;height:16px"/> Tenant baru</button>
    </div>

    <section class="panel">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Usaha</th><th>Paket</th><th>Langganan</th><th>Cabang</th><th>Pengguna</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($tenants as $t)
                    <tr wire:key="t-{{ $t->id }}">
                        <td><strong>{{ $t->name }}</strong><div class="muted small">{{ $t->slug }}{{ $t->business_type ? ', '.$t->business_type : '' }}</div></td>
                        <td class="small">{{ $t->subscription?->planLabel() }}</td>
                        <td class="small">
                            <span @class(['badge', 'ok' => $t->subscription?->allowsAccess(), 'bad' => ! $t->subscription?->allowsAccess()])>{{ $t->subscription?->statusLabel() }}</span>
                            @if ($t->subscription?->trial_ends_at)<div class="muted">Uji coba s.d. {{ $t->subscription->trial_ends_at->format('d/m/Y') }}</div>@endif
                        </td>
                        <td>{{ $t->branches_count }}</td>
                        <td>{{ $t->users_count }}</td>
                        <td><span @class(['badge', 'ok' => $t->isActive(), 'bad' => ! $t->isActive()])>{{ $t->isActive() ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="actions">
                            <button class="btn btn-ghost btn-sm" type="button" wire:click="editSubscription({{ $t->id }})">Langganan</button>
                            <button class="btn btn-sm {{ $t->isActive() ? 'btn-danger' : 'btn-ghost' }}" type="button" wire:click="toggleTenant({{ $t->id }})"
                                    @if($t->isActive()) wire:confirm="Nonaktifkan {{ $t->name }}? Penggunanya tidak bisa masuk, tetapi data tetap tersimpan." @endif>
                                {{ $t->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Belum ada tenant. Buat tenant pertama untuk mulai menyewakan aplikasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($subscriptionId)
        <div class="modal-backdrop">
            <form class="modal" wire:submit="saveSubscription" role="dialog" aria-modal="true">
                <div class="panel-head">
                    <h2>Ubah langganan</h2>
                    <button class="icon-btn" type="button" wire:click="$set('subscriptionId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="s-plan">Paket</label>
                            <select id="s-plan" class="select" wire:model="sub.plan">
                                @foreach ($plans as $k => $p)<option value="{{ $k }}">{{ $p['label'] }} ({{ $p['max_branches'] ?? '∞' }} cabang, {{ $p['max_users'] ?? '∞' }} pengguna)</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="s-status">Status</label>
                            <select id="s-status" class="select" wire:model="sub.status">
                                @foreach ($statuses as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field"><label for="s-trial">Akhir uji coba</label><input id="s-trial" type="date" class="input" wire:model="sub.trial_ends_at"></div>
                        <div class="field"><label for="s-period">Akhir periode berjalan</label><input id="s-period" type="date" class="input" wire:model="sub.current_period_ends_at"></div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('subscriptionId', null)">Batal</button>
                        <button class="btn btn-primary" type="submit">Simpan langganan</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($showForm)
        <div class="modal-backdrop">
            <form class="modal" wire:submit="save" role="dialog" aria-modal="true">
                <div class="panel-head">
                    <h2>Tenant baru</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showForm', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="t-name">Nama usaha</label>
                            <input id="t-name" class="input" wire:model="form.name" placeholder="Nama usaha penyewa">
                            @error('form.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="t-type">Jenis usaha</label>
                            <input id="t-type" class="input" wire:model="form.business_type" list="biz-types" placeholder="Pilih atau ketik">
                            <datalist id="biz-types">
                                @foreach (['Warung makan', 'Kedai kopi', 'Kedai minuman', 'Bakery', 'Bakso & mie', 'Ayam & seafood', 'Restoran', 'Food court', 'Street food'] as $bt)<option value="{{ $bt }}">@endforeach
                            </datalist>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label for="t-plan">Paket</label>
                            <select id="t-plan" class="select" wire:model="form.plan">
                                @foreach ($plans as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach
                            </select>
                        </div>
                        <label class="check" style="align-self:end;padding-bottom:10px"><input type="checkbox" wire:model="form.trial"> Mulai dengan masa uji coba {{ config('saas.trial_days') }} hari</label>
                    </div>
                    <h3>Akun pemilik</h3>
                    <div class="grid-2">
                        <div class="field">
                            <label for="t-oname">Nama</label>
                            <input id="t-oname" class="input" wire:model="form.owner_name" placeholder="Nama lengkap">
                            @error('form.owner_name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="t-oemail">Email</label>
                            <input id="t-oemail" class="input" type="email" wire:model="form.owner_email" placeholder="pemilik@usaha.com">
                            @error('form.owner_email') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="t-opass">Kata sandi awal</label>
                        <input id="t-opass" class="input" type="password" wire:model="form.owner_password" autocomplete="new-password">
                        <span class="hint">Minta pemilik menggantinya setelah masuk pertama kali.</span>
                        @error('form.owner_password') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <h3>Cabang pertama</h3>
                    <div class="grid-2">
                        <div class="field"><label for="t-bname">Nama cabang</label><input id="t-bname" class="input" wire:model="form.branch_name"></div>
                        <div class="field">
                            <label for="t-bcode">Kode cabang</label><input id="t-bcode" class="input" wire:model="form.branch_code">
                            @error('form.branch_code') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Buat tenant</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
