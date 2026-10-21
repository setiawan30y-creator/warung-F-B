<div class="panel-body">
    @switch($weather['status'])
        @case('ok')
            <div class="weather">
                <div class="temp">{{ $weather['temp'] }}°</div>
                <div>
                    <strong>{{ $weather['label'] }}</strong>
                    <p class="muted small">
                        Diperbarui {{ \Illuminate\Support\Carbon::parse($weather['updated_at'])->timezone($branch->timezone)->format('H:i') }}
                        @if ($weather['stale'])
                            <span class="badge warn" style="margin-left:4px">Data lama</span>
                        @endif
                    </p>
                </div>
                <button class="btn btn-ghost btn-sm right" wire:click="$refresh" type="button">Muat ulang</button>
            </div>
            @break
        @case('no_location')
            <p class="muted small">Koordinat cabang {{ $branch->name }} belum diisi. Tambahkan lintang dan bujur di pengaturan cabang agar cuaca bisa ditampilkan.</p>
            @break
        @case('failed')
            <div class="row">
                <p class="muted small">Cuaca gagal dimuat dan belum ada data sebelumnya.</p>
                <button class="btn btn-ghost btn-sm right" wire:click="$refresh" type="button">Coba lagi</button>
            </div>
            @break
        @default
            <p class="muted small">Pilih cabang aktif untuk melihat cuaca.</p>
    @endswitch
</div>
