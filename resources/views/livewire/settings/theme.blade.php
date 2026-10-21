<div class="stack">
    <div class="page-head">
        <div>
            <h1>Tema</h1>
            <p>Atur warna, huruf, dan mode tampilan untuk seluruh pengguna usaha ini. Setiap pengguna tetap bisa memilih mode terang atau gelap sendiri.</p>
        </div>
        <button class="btn btn-ghost" type="button" wire:click="resetDefaults"
                wire:confirm="Kembalikan semua pengaturan tema ke bawaan? Perubahan belum tersimpan sampai Anda menekan Simpan tema.">
            Kembalikan ke bawaan
        </button>
    </div>

    <div class="split">
        <form wire:submit="save" class="stack">
            <section class="panel">
                <div class="panel-head"><h2>Preset warna</h2></div>
                <div class="panel-body swatches">
                    @foreach ($presets as $key => $p)
                        <button type="button" class="swatch" wire:click="applyPreset('{{ $key }}')" aria-pressed="{{ $preset === $key ? 'true' : 'false' }}">
                            <span class="dots">
                                <i style="background:{{ $p['light']['primary'] }}"></i>
                                <i style="background:{{ $p['light']['accent'] }}"></i>
                                <i style="background:{{ $p['dark']['background'] }}"></i>
                            </span>
                            <span class="small" style="font-weight:600">{{ $p['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Warna kustom</h2>
                    <div class="seg" role="group" aria-label="Palet yang diedit">
                        <button type="button" wire:click="$set('editing','light')" aria-pressed="{{ $editing === 'light' ? 'true' : 'false' }}">Terang</button>
                        <button type="button" wire:click="$set('editing','dark')" aria-pressed="{{ $editing === 'dark' ? 'true' : 'false' }}">Gelap</button>
                    </div>
                </div>
                <div class="panel-body stack" style="gap:10px">
                    @foreach ($keys as $k => $label)
                        <div class="color-row" wire:key="c-{{ $editing }}-{{ $k }}">
                            <label class="small" for="c-{{ $k }}" style="font-weight:600">{{ $label }}</label>
                            <input type="color" id="c-{{ $k }}" wire:model.live.debounce.150ms="{{ $editing }}.{{ $k }}" aria-label="{{ $label }}">
                            <input class="input" style="font-size:13px;padding:6px 8px" wire:model.live.debounce.400ms="{{ $editing }}.{{ $k }}" aria-label="Kode HEX {{ $label }}">
                        </div>
                        @error($editing.'.'.$k) <span class="error">{{ $message }}</span> @enderror
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Huruf dan gaya</h2></div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="font">Jenis huruf</label>
                            <select id="font" class="select" wire:model="font">
                                @foreach (\App\Domain\Theme\ThemePalette::FONTS as $k => $f)
                                    <option value="{{ $k }}">{{ $f['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="radius">Sudut</label>
                            <select id="radius" class="select" wire:model="radius">
                                <option value="sm">Tegas</option>
                                <option value="md">Sedang</option>
                                <option value="lg">Membulat</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <span class="label">Mode bawaan</span>
                        <div class="seg" role="group">
                            @foreach (['system' => 'Ikuti perangkat', 'light' => 'Terang', 'dark' => 'Gelap'] as $m => $l)
                                <button type="button" wire:click="$set('mode','{{ $m }}')" aria-pressed="{{ $mode === $m ? 'true' : 'false' }}">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan tema</button>
            </div>
        </form>

        <aside class="stack" style="position:sticky;top:80px">
            <section class="panel">
                <div class="panel-head"><h2>Pratinjau {{ $editing === 'dark' ? 'gelap' : 'terang' }}</h2></div>
                <div class="panel-body">
                    @php $c = $palette; $r = \App\Domain\Theme\ThemePalette::RADII[$radius] ?? '10px'; @endphp
                    <div class="preview" style="background:{{ $c['background'] }};border-color:{{ $c['border'] }};color:{{ $c['text'] }};border-radius:{{ $r }};font-family:{{ \App\Domain\Theme\ThemePalette::FONTS[$font]['stack'] ?? 'inherit' }}">
                        <div style="padding:12px 14px;background:{{ $c['primary'] }};color:{{ $c['on_primary'] }};font-weight:700">Cabang Utama</div>
                        <div style="padding:14px;display:flex;flex-direction:column;gap:10px">
                            <div style="background:{{ $c['surface'] }};border:1px solid {{ $c['border'] }};border-radius:{{ $r }};padding:12px">
                                <strong>Nasi goreng spesial</strong>
                                <p style="color:{{ $c['muted'] }};font-size:13px;margin-top:2px">Telur mata sapi, kerupuk, acar</p>
                                <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
                                    <span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;color:{{ $c['success'] }};background:color-mix(in srgb,{{ $c['success'] }} 14%,transparent)">Tersedia</span>
                                    <span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;color:{{ $c['warning'] }};background:color-mix(in srgb,{{ $c['warning'] }} 15%,transparent)">Stok menipis</span>
                                    <span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;color:{{ $c['danger'] }};background:color-mix(in srgb,{{ $c['danger'] }} 13%,transparent)">Habis</span>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px">
                                <span style="padding:9px 14px;border-radius:8px;background:{{ $c['primary'] }};color:{{ $c['on_primary'] }};font-weight:600;font-size:14px">Bayar</span>
                                <span style="padding:9px 14px;border-radius:8px;border:1px solid {{ $c['accent'] }};color:{{ $c['accent'] }};font-weight:600;font-size:14px">Tambah catatan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Keterbacaan</h2></div>
                <div class="panel-body stack" style="gap:8px">
                    @foreach (['Terang' => $issuesLight, 'Gelap' => $issuesDark] as $label => $issues)
                        @if (empty($issues))
                            <p class="small"><span class="badge ok">{{ $label }}</span> Semua pasangan warna utama memenuhi kontras 4,5:1.</p>
                        @else
                            @foreach ($issues as $i)
                                <p class="small"><span class="badge warn">{{ $label }}</span> {{ $i['label'] }} kontrasnya {{ number_format($i['ratio'], 2, ',', '.') }}:1. Disarankan minimal 4,5:1.</p>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            </section>
        </aside>
    </div>
</div>
