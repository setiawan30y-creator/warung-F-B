<div class="stack" style="max-width:760px">
    <div class="page-head">
        <div>
            <h1>Akun saya</h1>
            <p>{{ $user->email }}, peran {{ $user->roles->pluck('name')->implode(', ') }}.</p>
        </div>
    </div>

    <form class="panel" wire:submit="saveProfile">
        <div class="panel-head"><h2>Profil</h2></div>
        <div class="panel-body stack">
            <div class="grid-2">
                <div class="field">
                    <label for="a-name">Nama</label>
                    <input id="a-name" class="input" wire:model="name">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="a-phone">Telepon</label>
                    <input id="a-phone" class="input" wire:model="phone" inputmode="tel">
                    @error('phone') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="field">
                <label for="a-mode">Mode tampilan</label>
                <select id="a-mode" class="select" wire:model="theme_mode" style="max-width:280px">
                    <option value="">Ikuti pengaturan usaha</option>
                    <option value="system">Ikuti perangkat</option>
                    <option value="light">Terang</option>
                    <option value="dark">Gelap</option>
                </select>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan profil</button></div>
        </div>
    </form>

    <form class="panel" wire:submit="savePassword">
        <div class="panel-head"><h2>Ganti kata sandi</h2></div>
        <div class="panel-body stack">
            <div class="field">
                <label for="a-cur">Kata sandi saat ini</label>
                <input id="a-cur" class="input" type="password" wire:model="current_password" autocomplete="current-password">
                @error('current_password') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="a-new">Kata sandi baru</label>
                    <input id="a-new" class="input" type="password" wire:model="password" autocomplete="new-password">
                    @error('password') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="a-conf">Ulangi kata sandi baru</label>
                    <input id="a-conf" class="input" type="password" wire:model="password_confirmation" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Ganti kata sandi</button></div>
        </div>
    </form>
</div>
