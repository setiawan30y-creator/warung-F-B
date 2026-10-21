<div class="stack">
    @php
        $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
        $pending = $setup->where('done', false);
    @endphp

    <div class="page-head">
        <div>
            <h1>{{ $greet }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
            <p>Ringkasan {{ $profile->brand_name ?? $tenant->name }}{{ $branch ? ' untuk cabang '.$branch->name : '' }}.</p>
        </div>
    </div>

    <section class="panel" aria-label="Ringkasan usaha">
        <dl class="facts" style="margin:0">
            <div class="fact">
                <dt>Cabang</dt>
                <dd>{{ $branchCount }} <small>{{ $branchLimit ? 'dari '.$branchLimit : 'tanpa batas' }}</small></dd>
            </div>
            <div class="fact">
                <dt>Pengguna aktif</dt>
                <dd>{{ $userCount }} <small>{{ $userLimit ? 'dari '.$userLimit : 'tanpa batas' }}</small></dd>
            </div>
            <div class="fact">
                <dt>Paket</dt>
                <dd>{{ $subscription?->planLabel() ?? '—' }}</dd>
            </div>
            <div class="fact">
                <dt>Status langganan</dt>
                <dd style="font-size:1rem;margin-top:10px">
                    <span @class(['badge', 'ok' => $subscription?->status === 'active', 'info' => $subscription?->status === 'trial', 'warn' => $subscription?->status === 'past_due'])>
                        {{ $subscription?->statusLabel() }}
                    </span>
                    @if ($subscription?->status === 'trial' && $subscription->trial_ends_at)
                        <small style="display:block;margin-top:6px">Berakhir {{ $subscription->trial_ends_at->translatedFormat('j F Y') }}</small>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <div class="split">
        <div class="stack">
            @if ($pending->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Siapkan usaha Anda</h2>
                            <p>{{ $setup->count() - $pending->count() }} dari {{ $setup->count() }} langkah selesai.</p>
                        </div>
                    </div>
                    <ul class="panel-body roadmap" style="padding-top:6px;padding-bottom:6px">
                        @foreach ($setup as $s)
                            <li>
                                <span @class(['badge', 'ok' => $s['done']])>{{ $s['done'] ? 'Selesai' : 'Belum' }}</span>
                                <span style="flex:1">{{ $s['label'] }}</span>
                                @unless ($s['done'])
                                    <a class="btn btn-ghost btn-sm" href="{{ route($s['route']) }}" wire:navigate>Buka</a>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($stockAlerts->isNotEmpty() || $pendingWaste)
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Perlu perhatian</h2>
                            <p>Stok di {{ $branch->name }} yang sudah di bawah batas minimum.</p>
                        </div>
                        <a class="small" href="{{ route('ops.inventory', ['lowOnly' => 1]) }}" wire:navigate>Lihat semua</a>
                    </div>
                    <ul class="panel-body roadmap" style="padding-top:6px;padding-bottom:6px">
                        @if ($pendingWaste)
                            <li>
                                <span class="badge warn">Susut</span>
                                <span style="flex:1">{{ $pendingWaste }} catatan susut menunggu persetujuan</span>
                                @if ($canApproveWaste)<a class="btn btn-ghost btn-sm" href="{{ route('ops.waste') }}" wire:navigate>Tinjau</a>@endif
                            </li>
                        @endif
                        @foreach ($stockAlerts->take(6) as $a)
                            <li>
                                <span @class(['badge', 'bad' => $a['qty'] <= 0, 'warn' => $a['qty'] > 0])>{{ $a['qty'] <= 0 ? 'Habis' : 'Menipis' }}</span>
                                <span style="flex:1">{{ $a['item']->name }}</span>
                                <span class="small muted num">{{ qty($a['qty']) }} dari minimum {{ qty($a['item']->min_stock) }} {{ $a['item']->unitCode() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Modul yang sedang dibangun</h2>
                        <p>Fondasi, menu, resep, dan stok sudah aktif. Modul berikut menyusul sesuai urutan roadmap.</p>
                    </div>
                </div>
                <ul class="panel-body roadmap" style="padding-top:6px;padding-bottom:6px">
                    <li><span class="phase">Fase 3</span><span>Kasir/POS, QR meja, Order Ahead, pembayaran, nota</span></li>
                    <li><span class="phase">Fase 4</span><span>Layar dapur, kapasitas, produksi batch</span></li>
                    <li><span class="phase">Fase 5</span><span>Closing kasir dan riwayatnya, saldo awal, keuangan</span></li>
                    <li><span class="phase">Fase 6</span><span>Karyawan, jadwal shift, absensi GPS, payroll</span></li>
                    <li><span class="phase">Fase 7</span><span>Paket langganan lanjutan, loyalitas, analitik</span></li>
                </ul>
            </section>
        </div>

        <div class="stack">
            @if ($profile?->show_weather_widget)
                <section class="panel">
                    <div class="panel-head"><h2>Cuaca di {{ $branch->name ?? 'cabang' }}</h2></div>
                    <livewire:weather-widget :branch-id="$branch?->id" :key="'w'.$branch?->id" />
                </section>
            @endif

            @if ($profile?->show_location_widget && $branch)
                <section class="panel">
                    <div class="panel-head"><h2>Lokasi cabang</h2></div>
                    <div class="panel-body stack" style="gap:8px">
                        <strong>{{ $branch->name }} <span class="muted small">({{ $branch->code }})</span></strong>
                        <p class="muted small">{{ collect([$branch->address, $branch->city])->filter()->implode(', ') ?: 'Alamat belum diisi.' }}</p>
                        @if ($branch->mapUrl())
                            <a class="btn btn-ghost btn-sm" style="align-self:flex-start" href="{{ $branch->mapUrl() }}" target="_blank" rel="noopener">
                                <x-icon name="pin" :size="16"/> Buka di peta
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            @if ($recent->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <h2>Aktivitas terbaru</h2>
                        <a class="small" href="{{ route('settings.audit') }}" wire:navigate>Lihat semua</a>
                    </div>
                    <ul class="panel-body roadmap" style="padding-top:4px;padding-bottom:4px">
                        @foreach ($recent as $log)
                            <li class="small">
                                <span style="flex:1"><strong>{{ $log->user->name ?? 'Sistem' }}</strong> {{ $log->eventLabel() }}</span>
                                <span class="muted">{{ $log->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</div>
