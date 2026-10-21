<div class="stack">
    <div class="page-head">
        <div>
            <h1>Produksi racikan</h1>
            <p>Catat setiap kali membuat saus, bumbu, atau racikan lain di {{ $branch->name }}. Bahan baku berkurang sesuai pemakaian aktual, dan stok racikan bertambah sesuai hasil yang ditimbang.</p>
        </div>
        <button class="btn btn-primary" type="button" wire:click="$set('showCreate', true)"><x-icon name="plus" :size="16"/> Produksi baru</button>
    </div>

    <section class="panel">
        <div class="filters">
            <div class="field">
                <label for="p-status">Status</label>
                <select id="p-status" class="select" wire:model.live="status">
                    <option value="">Semua</option>
                    @foreach (\App\Models\ProductionBatch::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Racikan</th><th class="num">Rencana</th><th class="num">Hasil</th><th class="num">HPP per satuan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($batches as $b)
                    @php $u = $b->outputItem->unitCode(); @endphp
                    <tr wire:key="pb-{{ $b->id }}">
                        <td class="small"><strong>{{ $b->number }}</strong><div class="muted">{{ $b->created_at->timezone($branch->timezone)->format('d/m/Y H:i') }}</div></td>
                        <td>{{ $b->outputItem->name }}</td>
                        <td class="num">{{ qty($b->planned_output) }} {{ $u }}</td>
                        <td class="num">{{ $b->actual_output !== null ? qty($b->actual_output).' '.$u : '—' }}</td>
                        <td class="num">{{ $b->unit_cost !== null ? rupiah($b->unit_cost, 2) : '—' }}</td>
                        <td><span @class(['badge', 'ok' => $b->status === 'posted', 'info' => $b->status === 'draft'])>{{ \App\Models\ProductionBatch::STATUSES[$b->status] }}</span></td>
                        <td class="actions"><button class="btn btn-ghost btn-sm" type="button" wire:click="open({{ $b->id }})">{{ $b->status === 'draft' ? 'Lanjutkan' : 'Lihat' }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty"><strong>Belum ada produksi</strong>Buat racikan terlebih dahulu di halaman Resep, lalu catat produksinya di sini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())<div class="panel-body">{{ $batches->links('partials.pagination') }}</div>@endif
    </section>

    @if ($showCreate)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showCreate', false)">
            <form class="modal" wire:submit="create" role="dialog" aria-modal="true">
                <div class="panel-head"><h2>Produksi baru</h2><button class="icon-btn" type="button" wire:click="$set('showCreate', false)" aria-label="Tutup"><x-icon name="x"/></button></div>
                <div class="panel-body stack">
                    @if ($recipes->isEmpty())
                        <div class="alert warn">Belum ada racikan dengan komposisi. Atur komposisinya di halaman Resep.</div>
                    @endif
                    <div class="grid-2">
                        <div class="field">
                            <label for="pc-recipe">Racikan</label>
                            <select id="pc-recipe" class="select" wire:model.live="recipeId">
                                <option value="">Pilih racikan</option>
                                @foreach ($recipes as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                            </select>
                            @error('recipeId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        @php $sel = $recipes->firstWhere('id', (int) $recipeId); @endphp
                        <div class="field">
                            <label for="pc-planned">Rencana hasil{{ $sel ? ' ('.$sel->outputItem->unitCode().')' : '' }}</label>
                            <input id="pc-planned" class="input" type="number" step="any" min="0" wire:model="planned">
                            @if ($sel)<span class="hint">Satu resep menghasilkan {{ qty($sel->currentVersion->yield_quantity) }} {{ $sel->outputItem->unitCode() }}.</span>@endif
                            @error('planned') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showCreate', false)">Batal</button>
                        <button class="btn btn-primary" type="submit">Hitung kebutuhan bahan</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($batch)
        @php $u = $batch->outputItem->unitCode(); $draft = $batch->status === 'draft'; @endphp
        <div class="modal-backdrop" wire:keydown.escape.window="$set('batchId', null)">
            <form class="modal wide" wire:submit="post" role="dialog" aria-modal="true" aria-labelledby="batch-title">
                <div class="panel-head">
                    <div>
                        <h2 id="batch-title">{{ $batch->outputItem->name }}</h2>
                        <p>{{ $batch->number }}, versi resep {{ $batch->version->version }}, rencana {{ qty($batch->planned_output) }} {{ $u }}</p>
                    </div>
                    <button class="icon-btn" type="button" wire:click="$set('batchId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Bahan</th><th class="num">Kebutuhan resep</th><th>{{ $draft ? 'Pemakaian aktual' : 'Dipakai' }}</th>@if($draft)<th class="num">Stok tersedia</th>@else<th class="num">Biaya</th>@endif</tr></thead>
                        <tbody>
                        @foreach ($batch->consumptions as $c)
                            @php $cu = $c->item->unitCode(); $short = $draft && ($available[$c->id] ?? 0) < (float) ($actuals[$c->id] ?? 0); @endphp
                            <tr wire:key="pcl-{{ $c->id }}">
                                <td><strong>{{ $c->item->name }}</strong></td>
                                <td class="num">{{ qty($c->planned_quantity) }} {{ $cu }}</td>
                                <td style="min-width:150px">
                                    @if ($draft)
                                        <div class="row" style="flex-wrap:nowrap"><input class="input" type="number" step="any" min="0" wire:model="actuals.{{ $c->id }}" aria-label="Pemakaian aktual {{ $c->item->name }}"><span class="small">{{ $cu }}</span></div>
                                    @else
                                        {{ qty($c->actual_quantity) }} {{ $cu }}
                                    @endif
                                </td>
                                @if ($draft)
                                    <td class="num {{ $short ? 'neg' : '' }}">{{ qty($available[$c->id] ?? 0) }} {{ $cu }}</td>
                                @else
                                    <td class="num">{{ rupiah($c->total_cost) }}</td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-body stack">
                    @if ($draft)
                        <div class="grid-2">
                            <div class="field">
                                <label for="b-out">Hasil aktual ({{ $u }})</label>
                                <input id="b-out" class="input" type="number" step="any" min="0" wire:model="actualOutput">
                                <span class="hint">Timbang atau ukur hasil jadi setelah diolah.</span>
                            </div>
                            <div class="field">
                                <label for="b-reason">Alasan selisih</label>
                                <input id="b-reason" class="input" wire:model="varianceReason" placeholder="Wajib bila hasil berbeda lebih dari {{ (int) (config('inventory.production_variance_tolerance') * 100) }}%">
                            </div>
                        </div>
                        @error('actualOutput') <div class="alert bad">{{ $message }}</div> @enderror
                        <div class="form-actions">
                            <button class="btn btn-danger" type="button" style="margin-right:auto" wire:click="cancel" wire:confirm="Batalkan batch {{ $batch->number }}? Stok tidak berubah.">Batalkan batch</button>
                            <button class="btn btn-ghost" type="button" wire:click="$set('batchId', null)">Simpan nanti</button>
                            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:confirm="Posting produksi? Stok bahan akan dikurangi dan stok racikan bertambah. Tindakan ini tidak dapat dibatalkan.">Posting produksi</button>
                        </div>
                    @else
                        <div class="sum-row"><span>Hasil aktual</span><strong>{{ qty($batch->actual_output) }} {{ $u }}</strong></div>
                        <div class="sum-row"><span>Selisih dari rencana</span><strong @class(['neg' => ($batch->yieldVariance() ?? 0) < 0])>{{ qty($batch->yieldVariance()) }} {{ $u }}</strong></div>
                        @if ($batch->variance_reason)<div class="sum-row"><span>Alasan selisih</span><span>{{ $batch->variance_reason }}</span></div>@endif
                        <div class="sum-row"><span>Total biaya bahan</span><strong>{{ rupiah($batch->total_cost) }}</strong></div>
                        <div class="sum-row"><span>HPP per {{ $u }}</span><strong>{{ rupiah($batch->unit_cost, 2) }}</strong></div>
                    @endif
                </div>
            </form>
        </div>
    @endif
</div>
