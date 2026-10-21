<div class="stack">
    <div class="page-head">
        <div>
            <a class="small" href="{{ route('ops.recipes', ['tab' => $recipe->isSub() ? 'sub' : 'menu']) }}" wire:navigate>Kembali ke daftar resep</a>
            <h1 style="margin-top:6px">{{ $recipe->name }}</h1>
            <p>
                @if ($recipe->isSub())
                    Racikan dengan hasil dalam {{ $outputUnit }}. Biaya per {{ $outputUnit }} dipakai oleh setiap menu yang memakai racikan ini.
                @else
                    Takaran untuk satu porsi atau sejumlah porsi. Racikan dihitung dari HPP racikan, bukan dari bahan mentahnya, sehingga biayanya tidak terhitung dua kali.
                @endif
            </p>
        </div>
    </div>

    <div class="split">
        <form class="panel" wire:submit="save">
            <div class="panel-head">
                <div>
                    <h2>Komposisi</h2>
                    <p>{{ $recipe->currentVersion ? 'Versi aktif: '.$recipe->currentVersion->version.'. Menyimpan perubahan membuat versi baru; versi lama tetap tersimpan untuk HPP historis.' : 'Belum ada komposisi.' }}</p>
                </div>
            </div>
            <div class="panel-body stack">
                <div class="lines">
                    <div class="line head"><span>Bahan</span><span>Takaran</span><span>Satuan</span><span style="text-align:right">Biaya</span><span></span></div>
                    @foreach ($lines as $i => $l)
                        @php $p = $preview[$i] ?? []; $opts = $unitMap[(int) ($l['item'] ?? 0)] ?? []; @endphp
                        <div class="line" wire:key="line-{{ $i }}">
                            <select class="select" wire:model.live="lines.{{ $i }}.item" aria-label="Bahan baris {{ $i + 1 }}">
                                <option value="">Pilih bahan</option>
                                <optgroup label="Bahan beli">
                                    @foreach ($items->where('type', 'purchased') as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach
                                </optgroup>
                                <optgroup label="Hasil racikan">
                                    @foreach ($items->where('type', 'produced') as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach
                                </optgroup>
                            </select>
                            <input class="input" type="number" step="any" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.qty" placeholder="0" aria-label="Takaran baris {{ $i + 1 }}">
                            <select class="select" wire:model.live="lines.{{ $i }}.unit" aria-label="Satuan baris {{ $i + 1 }}">
                                <option value="">—</option>
                                @foreach ($opts as $o)<option value="{{ $o['id'] }}">{{ $o['code'] }}</option>@endforeach
                            </select>
                            <span class="cost">
                                @if (($p['cost'] ?? null) !== null)
                                    {{ rupiah($p['cost'], 0) }}
                                    <span class="muted" style="display:block;font-size:11.5px">{{ $p['source'] }}</span>
                                @elseif ($p['error'] ?? null)
                                    <span class="neg" style="font-size:12px">{{ $p['error'] }}</span>
                                @else — @endif
                            </span>
                            <button class="icon-btn" type="button" wire:click="removeLine({{ $i }})" aria-label="Hapus baris {{ $i + 1 }}"><x-icon name="x" :size="16"/></button>
                        </div>
                        @error('lines.'.$i.'.item') <span class="error">{{ $message }}</span> @enderror
                        @error('lines.'.$i.'.qty') <span class="error">{{ $message }}</span> @enderror
                        @error('lines.'.$i.'.unit') <span class="error">{{ $message }}</span> @enderror
                    @endforeach
                    <div><button class="btn btn-ghost btn-sm" type="button" wire:click="addLine"><x-icon name="plus" :size="16"/> Tambah bahan</button></div>
                    @error('lines') <div class="alert bad">{{ $message }}</div> @enderror
                </div>

                <div class="grid-3">
                    <div class="field">
                        <label for="re-yield">{{ $recipe->isSub() ? 'Hasil aktual ('.$outputUnit.')' : 'Jumlah porsi' }}</label>
                        <input id="re-yield" class="input" type="number" step="any" min="0" wire:model.live.debounce.400ms="yield">
                        <span class="hint">{{ $recipe->isSub() ? 'Berat atau volume jadi setelah susut, bukan jumlah bahan.' : 'Isi lebih dari 1 bila takaran untuk beberapa porsi sekaligus.' }}</span>
                        @error('yield') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="re-from">Berlaku mulai</label>
                        <input id="re-from" class="input" type="date" wire:model="effectiveFrom">
                    </div>
                    <div class="field">
                        <label for="re-notes">Catatan versi</label>
                        <input id="re-notes" class="input" wire:model="notes" placeholder="Contoh: kurangi gula">
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan resep</button></div>
            </div>
        </form>

        <aside class="stack">
            <section class="panel">
                <div class="panel-head"><h2>HPP di {{ $branch->name ?? 'cabang aktif' }}</h2></div>
                <div class="panel-body">
                    <div class="sum-row"><span>Total biaya bahan</span><strong>{{ rupiah($total, 0) }}</strong></div>
                    <div class="sum-row">
                        <span>{{ $recipe->isSub() ? 'HPP per '.$outputUnit : 'HPP per porsi' }}</span>
                        <strong class="big-number">{{ $perYield !== null ? rupiah($perYield, $recipe->isSub() ? 2 : 0) : '—' }}</strong>
                    </div>
                    @if (! $recipe->isSub())
                        <div class="sum-row"><span>Harga jual</span><strong>{{ rupiah($price) }}</strong></div>
                        <div class="sum-row"><span>Margin</span><strong @class(['neg' => $margin !== null && $margin < 0.3])>{{ $margin !== null ? number_format($margin * 100, 1, ',', '.').'%' : '—' }}</strong></div>
                    @endif
                    <p class="muted small" style="margin-top:10px">Biaya memakai rata-rata stok cabang. Bila stok belum ada, dipakai harga beli terakhir, atau HPP resep untuk racikan.</p>
                </div>
            </section>

            @unless ($recipe->isSub())
                <section class="panel">
                    <div class="panel-head"><h2>Simulasi harga jual</h2></div>
                    <div class="panel-body stack" style="gap:10px">
                        <div class="field">
                            <label for="re-margin">Target margin (%)</label>
                            <input id="re-margin" class="input" type="number" min="0" max="95" wire:model.live.debounce.300ms="targetMargin">
                        </div>
                        <div class="sum-row"><span>Saran harga, dibulatkan ke Rp500</span><strong>{{ $suggested !== null ? rupiah($suggested) : '—' }}</strong></div>
                        <p class="muted small">Ubah harga jual di halaman Menu dan harga.</p>
                    </div>
                </section>
            @endunless

            <section class="panel">
                <div class="panel-head"><h2>Riwayat versi</h2></div>
                <ul class="panel-body roadmap" style="list-style:none;margin:0;padding-top:4px;padding-bottom:4px">
                    @forelse ($versions as $v)
                        <li class="small" style="display:block">
                            <strong>Versi {{ $v->version }}</strong>
                            @if ($v->id === $recipe->current_version_id)<span class="badge ok" style="margin-left:6px">Aktif</span>@endif
                            <div class="muted">{{ $v->items_count }} bahan, hasil {{ qty($v->yield_quantity) }}{{ $outputUnit ? ' '.$outputUnit : ' porsi' }}, berlaku {{ $v->effective_from->format('d/m/Y') }}, oleh {{ $v->author->name ?? 'Sistem' }}</div>
                            @if ($v->notes)<div>{{ $v->notes }}</div>@endif
                        </li>
                    @empty
                        <li class="muted small">Belum ada versi.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
