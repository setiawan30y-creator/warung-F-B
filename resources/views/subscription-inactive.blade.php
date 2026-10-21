@extends('layouts.guest', ['title' => 'Langganan tidak aktif'])

@section('content')
<div class="auth-form" style="min-height:100vh">
    <div class="panel" style="max-width:480px">
        <div class="panel-body stack">
            <h2>Akses usaha sedang dinonaktifkan</h2>
            <p class="muted">
                Langganan {{ $currentTenant->name }} berstatus
                <strong>{{ $currentTenant->subscription?->statusLabel() ?? 'tidak ditemukan' }}</strong>.
                Semua data tetap tersimpan dan akan tersedia kembali setelah langganan diaktifkan.
                Hubungi pengelola platform untuk memperpanjang.
            </p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost" type="submit">Keluar</button>
            </form>
        </div>
    </div>
</div>
@endsection
