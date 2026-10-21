<div class="stack">
    <div class="page-head">
        <div>
            <h1>Profil perusahaan</h1>
            <p>Identitas ini dipakai di aplikasi, nota, dan dokumen. Hanya tenant Anda yang dapat melihat atau mengubahnya.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Logo</h2>
                <p>PNG, JPG, atau WebP. Maksimal 2 MB, sisi minimal 64 piksel.</p>
            </div>
        </div>
        <div class="panel-body row" style="gap:20px;align-items:flex-start">
            <div class="logo-box">
                @if ($logo && ! $errors->has('logo'))
                    <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo baru">
                @elseif ($profile->logoUrl())
                    <img src="{{ $profile->logoUrl() }}" alt="Logo saat ini">
                @else
                    <span class="muted small">Belum ada logo</span>
                @endif
            </div>
            <div class="stack" style="gap:10px;flex:1;min-width:220px">
                <div class="field">
                    <label for="logo">Pilih berkas</label>
                    <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="input">
                    <div wire:loading wire:target="logo" class="hint">Mengunggah…</div>
                    @error('logo') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="row">
                    @if ($logo && ! $errors->has('logo'))
                        <button class="btn btn-primary btn-sm" wire:click="saveLogo" type="button">Simpan logo</button>
                        <button class="btn btn-ghost btn-sm" wire:click="$set('logo', null)" type="button">Batal</button>
                    @elseif ($profile->logo_path)
                        <button class="btn btn-danger btn-sm" type="button" wire:click="removeLogo"
                                wire:confirm="Hapus logo usaha? Logo tidak lagi tampil di aplikasi dan nota.">Hapus logo</button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <form wire:submit="save" class="panel">
        <div class="panel-head"><h2>Identitas dan kontak</h2></div>
        <div class="panel-body stack">
            <div class="grid-2">
                <div class="field">
                    <label for="brand_name">Nama usaha</label>
                    <input id="brand_name" class="input" wire:model="brand_name" placeholder="Nama yang tampil ke pelanggan">
                    @error('brand_name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="legal_name">Nama badan usaha</label>
                    <input id="legal_name" class="input" wire:model="legal_name" placeholder="Contoh: CV Nama Usaha">
                    @error('legal_name') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="address">Alamat</label>
                    <input id="address" class="input" wire:model="address" placeholder="Jalan, nomor, kelurahan">
                    @error('address') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="city">Kota</label>
                    <input id="city" class="input" wire:model="city" placeholder="Kota/kabupaten">
                </div>
            </div>
            <div class="grid-3">
                <div class="field">
                    <label for="phone">Telepon</label>
                    <input id="phone" class="input" wire:model="phone" inputmode="tel" placeholder="021 1234 5678">
                    @error('phone') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="whatsapp">WhatsApp</label>
                    <input id="whatsapp" class="input" wire:model="whatsapp" inputmode="tel" placeholder="0812 3456 7890">
                    @error('whatsapp') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" class="input" type="email" wire:model="email" placeholder="halo@usaha.com">
                    @error('email') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="grid-3">
                <div class="field">
                    <label for="website">Situs web</label>
                    <input id="website" class="input" wire:model="website" placeholder="https://">
                    @error('website') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="nib">NIB</label>
                    <input id="nib" class="input" wire:model="business_id_number">
                </div>
                <div class="field">
                    <label for="npwp">NPWP</label>
                    <input id="npwp" class="input" wire:model="tax_id_number">
                </div>
            </div>

            <hr class="div" style="margin:4px 0">
            <h3>Teks pada nota</h3>
            <div class="grid-2">
                <div class="field">
                    <label for="rh">Kepala nota</label>
                    <input id="rh" class="input" wire:model="receipt_header" placeholder="Contoh: Terima kasih sudah mampir">
                </div>
                <div class="field">
                    <label for="rf">Kaki nota</label>
                    <input id="rf" class="input" wire:model="receipt_footer" placeholder="Contoh: Ikuti kami di media sosial">
                </div>
            </div>

            <hr class="div" style="margin:4px 0">
            <h3>Widget beranda</h3>
            <label class="check"><input type="checkbox" wire:model="show_weather_widget"> Tampilkan cuaca cabang aktif</label>
            <label class="check"><input type="checkbox" wire:model="show_location_widget"> Tampilkan lokasi cabang aktif</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan profil</button>
            </div>
        </div>
    </form>
</div>
