@extends('layouts.guest', ['title' => 'Masuk'])

@section('content')
<div class="auth">
    <section class="auth-side">
        <div class="auth-mark">ALMARA</div>
        <div>
            <h1>Dapur, kasir, dan cabang dalam satu catatan.</h1>
            <p>Masuk untuk mengelola usaha makanan dan minuman Anda, dari satu kasir hingga banyak cabang.</p>
        </div>
        <small style="opacity:.75">Restaurant &amp; Beverage OS, dikembangkan oleh @2026-Alifiandra</small>
    </section>

    <section class="auth-form">
        <form method="POST" action="{{ route('login.store') }}" class="stack" novalidate>
            @csrf
            <div>
                <h2 style="font-size:1.4rem">Masuk</h2>
                <p class="muted small" style="margin-top:4px">Gunakan email dan kata sandi akun Anda.</p>
            </div>

            @if ($errors->any())
                <div class="alert bad" role="alert">{{ $errors->first() }}</div>
            @endif

            <div class="field">
                <label for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}"
                       autocomplete="username" placeholder="nama@usaha.com" required autofocus>
            </div>
            <div class="field">
                <label for="password">Kata sandi</label>
                <input class="input" id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <label class="check small">
                <input type="checkbox" name="remember" value="1"> Tetap masuk di perangkat ini
            </label>
            <button class="btn btn-primary" type="submit" style="width:100%;padding:12px">Masuk</button>
        </form>
    </section>
</div>
@endsection
