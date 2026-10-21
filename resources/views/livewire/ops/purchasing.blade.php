<div class="stack">
    <div class="page-head">
        <div>
            <h1>Pembelian</h1>
            <p>Pesanan pembelian untuk {{ $branch->name }}. Stok baru bertambah saat barang diterima, dan harga acuan bahan ikut diperbarui. Utang dan pembayaran supplier menyusul di modul keuangan.</p>
        </div>
        @if ($tab === 'po')
            <button class="btn btn-primary" type="button" wire:click="newPo" @disabled($suppliers->where('is_active', true)->isEmpty())><x-icon name="plus" :size="16"/> PO baru</button>
        @else
            <button class="btn btn-primary" type="button" wire:click="newSupplier"><x-icon name="plus" :size="16"/> Supplier baru</button>
        @endif
    </div>

    <nav class="tabs" aria-label="Pembelian">
        <button type="button" wire:click="$set('tab', 'po')" aria-selected="{{ $tab === 'po' ? 'true' : 'false' }}">Pesanan pembelian</button>
        <button type="button" wire:click="$set('tab', 'supplier')" aria-selected="{{ $tab === 'supplier' ? 'true' : 'false' }}">Supplier</button>
    </nav>

    @if ($tab === 'po')
        @if ($suppliers->where('is_active', true)->isEmpty())
            <div class="alert warn">Tambahkan supplier terlebih dahulu di tab Supplier sebelum membuat PO.</div>
        @endif
        <section class="panel">
            <div class="filters">
                <div class="field">
                    <label for="po-status">Status</label>
                    <select id="po-status" class="select" wire:model.live="status">
                        <option value="">Semua</option>
                        @foreach (\App\Models\PurchaseOrder::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nomor</th><th>Supplier</th><th>Tanggal</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($orders as $o)
                        <tr wire:key="po-{{ $o->id }}">
                            <td class="small"><strong>{{ $o->number }}</strong><div class="muted">{{ $o->items_count }} bahan</div></td>
                            <td>{{ $o->supplier->name }}</td>
                            <td class="small">{{ $o->order_date->format('d/m/Y') }}@if($o->expected_date)<div class="muted">tiba {{ $o->expected_date->format('d/m/Y') }}</div>@endif</td>
                            <td class="num">{{ rupiah($o->total) }}</td>
                            <td><span @class(['badge', 'ok' => $o->status === 'received', 'info' => in_array($o->status, ['ordered', 'partial']), 'bad' => $o->status === 'cancelled'])>{{ $o->statusLabel() }}</span></td>
                            <td class="actions">
                                @if ($o->status === 'draft')
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="editPo({{ $o->id }})">Ubah</button>
                                    <button class="btn btn-ghost btn-sm" type="button" wire:click="markOrdered({{ $o->id }})">Tandai dipesan</button>
                                @endif
                                @if ($o->canReceive())
                                    <button class="btn btn-primary btn-sm" type="button" wire:click="openReceive({{ $o->id }})">Terima barang</button>
                                @endif
                                @if (in_array($o->status, ['draft', 'ordered']))
                                    <button class="btn btn-danger btn-sm" type="button" wire:click="cancelPo({{ $o->id }})" wire:confirm="Batalkan {{ $o->number }}?">Batalkan</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty"><strong>Belum ada pesanan pembelian</strong>Buat PO untuk mencatat bahan yang dipesan ke supplier.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())<div class="panel-body">{{ $orders->links('partials.pagination') }}</div>@endif
        </section>
    @else
        <section class="panel">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Supplier</th><th>Kontak</th><th>Waktu kirim</th><th>PO</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($suppliers as $s)
                        <tr wire:key="sp-{{ $s->id }}">
                            <td><strong>{{ $s->name }}</strong>@if($s->address)<div class="muted small">{{ $s->address }}</div>@endif</td>
                            <td class="small">{{ $s->contact_name ?? '—' }}<div class="muted">{{ $s->phone }}</div></td>
                            <td class="small">{{ $s->lead_time_days !== null ? $s->lead_time_days.' hari' : '—' }}</td>
                            <td class="small">{{ $s->purchase_orders_count }}</td>
                            <td><span @class(['badge', 'ok' => $s->is_active])>{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="actions"><button class="btn btn-ghost btn-sm" type="button" wire:click="editSupplier({{ $s->id }})">Ubah</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty"><strong>Belum ada supplier</strong>Tambahkan pemasok bahan Anda beserta kontaknya.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($showPo)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showPo', false)">
            <form class="modal wide" wire:submit="savePo" role="dialog" aria-modal="true" aria-labelledby="po-title">
                <div class="panel-head">
                    <h2 id="po-title">{{ $poId ? 'Ubah PO' : 'PO baru' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showPo', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-3">
                        <div class="field">
                            <label for="po-sup">Supplier</label>
                            <select id="po-sup" class="select" wire:model="po.supplier_id">
                                <option value="">Pilih supplier</option>
                                @foreach ($suppliers->where('is_active', true) as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                            @error('po.supplier_id') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="po-date">Tanggal pesan</label>
                            <input id="po-date" class="input" type="date" wire:model="po.order_date">
                        </div>
                        <div class="field">
                            <label for="po-exp">Perkiraan tiba</label>
                            <input id="po-exp" class="input" type="date" wire:model="po.expected_date">
                            @error('po.expected_date') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="lines">
                        <div class="line head"><span>Bahan</span><span>Jumlah</span><span>Satuan</span><span style="text-align:right">Harga per satuan (Rp)</span><span></span></div>
                        @foreach ($lines as $i => $l)
                            <div class="line" wire:key="pol-{{ $i }}">
                                <select class="select" wire:model.live="lines.{{ $i }}.item" aria-label="Bahan baris {{ $i + 1 }}">
                                    <option value="">Pilih bahan</option>
                                    @foreach ($items as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach
                                </select>
                                <input class="input" type="number" step="any" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.quantity" aria-label="Jumlah baris {{ $i + 1 }}">
                                <select class="select" wire:model.live="lines.{{ $i }}.unit" aria-label="Satuan baris {{ $i + 1 }}">
                                    <option value="">—</option>
                                    @foreach ($unitMap[(int) ($l['item'] ?? 0)] ?? [] as $o)<option value="{{ $o['id'] }}">{{ $o['code'] }}</option>@endforeach
                                </select>
                                <input class="input" type="number" step="any" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.price" style="text-align:right" aria-label="Harga baris {{ $i + 1 }}">
                                <button class="icon-btn" type="button" wire:click="removeLine({{ $i }})" aria-label="Hapus baris {{ $i + 1 }}"><x-icon name="x" :size="16"/></button>
                            </div>
                            @foreach (['item', 'quantity', 'unit', 'price'] as $f)
                                @error('lines.'.$i.'.'.$f) <span class="error">{{ $message }}</span> @enderror
                            @endforeach
                        @endforeach
                        <div class="row">
                            <button class="btn btn-ghost btn-sm" type="button" wire:click="addLine"><x-icon name="plus" :size="16"/> Tambah bahan</button>
                            <span class="right"><span class="muted small">Total</span> <strong class="big-number" style="font-size:1.2rem">{{ rupiah($poTotal) }}</strong></span>
                        </div>
                        @error('lines') <div class="alert bad">{{ $message }}</div> @enderror
                    </div>
                    <div class="field"><label for="po-notes">Catatan</label><input id="po-notes" class="input" wire:model="po.notes"></div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showPo', false)">Batal</button>
                        <button class="btn btn-ghost" type="submit">Simpan draf</button>
                        <button class="btn btn-primary" type="button" wire:click="orderPo">Simpan dan tandai dipesan</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($receivePo)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('receiveId', null)">
            <form class="modal wide" wire:submit="saveReceive" role="dialog" aria-modal="true" aria-labelledby="rcv-title">
                <div class="panel-head">
                    <div><h2 id="rcv-title">Terima barang {{ $receivePo->number }}</h2><p>{{ $receivePo->supplier->name }}. Isi jumlah yang benar-benar datang; sisanya bisa diterima nanti.</p></div>
                    <button class="icon-btn" type="button" wire:click="$set('receiveId', null)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Bahan</th><th class="num">Dipesan</th><th class="num">Sudah diterima</th><th>Diterima sekarang</th></tr></thead>
                        <tbody>
                        @foreach ($receivePo->items as $l)
                            @php $ratio = $l->quantity / $l->base_quantity; @endphp
                            <tr wire:key="rl-{{ $l->id }}">
                                <td><strong>{{ $l->item->name }}</strong><div class="muted small">{{ rupiah($l->unit_price) }} per {{ $l->unit->code }}</div></td>
                                <td class="num">{{ qty($l->quantity) }} {{ $l->unit->code }}</td>
                                <td class="num">{{ qty($l->received_base_quantity * $ratio) }} {{ $l->unit->code }}</td>
                                <td style="min-width:150px">
                                    @if ($l->remainingBase() > 0)
                                        <div class="row" style="flex-wrap:nowrap"><input class="input" type="number" step="any" min="0" wire:model="receive.{{ $l->id }}" aria-label="Diterima {{ $l->item->name }}"><span class="small">{{ $l->unit->code }}</span></div>
                                    @else
                                        <span class="badge ok">Lengkap</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-body stack">
                    @error('receive') <div class="alert bad">{{ $message }}</div> @enderror
                    <div class="field"><label for="rcv-notes">Catatan penerimaan</label><input id="rcv-notes" class="input" wire:model="receiveNotes" placeholder="Contoh: nomor surat jalan"></div>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('receiveId', null)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Catat penerimaan</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($showSupplier)
        <div class="modal-backdrop" wire:keydown.escape.window="$set('showSupplier', false)">
            <form class="modal" wire:submit="saveSupplier" role="dialog" aria-modal="true" aria-labelledby="sup-title">
                <div class="panel-head">
                    <h2 id="sup-title">{{ $supplierId ? 'Ubah supplier' : 'Supplier baru' }}</h2>
                    <button class="icon-btn" type="button" wire:click="$set('showSupplier', false)" aria-label="Tutup"><x-icon name="x"/></button>
                </div>
                <div class="panel-body stack">
                    <div class="grid-2">
                        <div class="field">
                            <label for="sp-name">Nama supplier</label>
                            <input id="sp-name" class="input" wire:model="supplier.name" placeholder="Nama toko atau perusahaan">
                            @error('supplier.name') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="sp-contact">Nama kontak</label>
                            <input id="sp-contact" class="input" wire:model="supplier.contact_name" placeholder="Nama lengkap">
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="field">
                            <label for="sp-phone">Telepon/WhatsApp</label>
                            <input id="sp-phone" class="input" wire:model="supplier.phone" inputmode="tel">
                            @error('supplier.phone') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="sp-email">Email</label>
                            <input id="sp-email" class="input" type="email" wire:model="supplier.email">
                            @error('supplier.email') <span class="error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="sp-lead">Waktu kirim (hari)</label>
                            <input id="sp-lead" class="input" type="number" min="0" wire:model="supplier.lead_time_days">
                        </div>
                    </div>
                    <div class="field"><label for="sp-addr">Alamat</label><input id="sp-addr" class="input" wire:model="supplier.address"></div>
                    <div class="field"><label for="sp-notes">Catatan</label><input id="sp-notes" class="input" wire:model="supplier.notes" placeholder="Contoh: minimal order, jadwal kirim"></div>
                    <label class="check"><input type="checkbox" wire:model="supplier.is_active"> Supplier aktif</label>
                    <div class="form-actions">
                        <button class="btn btn-ghost" type="button" wire:click="$set('showSupplier', false)">Batal</button>
                        <button class="btn btn-primary" type="submit">Simpan supplier</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
