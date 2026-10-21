<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body>
<div class="main" style="min-height:100vh">
    <header class="topbar">
        <strong>ALMARA Platform</strong>
        <span class="muted small">Pengelolaan tenant</span>
        <div class="spacer"></div>
        <span class="small">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="icon-btn" type="submit" aria-label="Keluar"><x-icon name="logout"/></button>
        </form>
    </header>
    <main class="content" style="margin:0 auto">{{ $slot }}</main>
    <footer class="footer-sign">ALMARA Restaurant &amp; Beverage OS, dikembangkan oleh @2026-Alifiandra</footer>
</div>
<div x-data="{ show: false, msg: '' }"
     x-on:toast.window="msg = $event.detail.message ?? ''; show = true; setTimeout(() => show = false, 3200)"
     x-show="show" x-cloak class="toast" role="status" x-text="msg"></div>
@livewireScripts
</body>
</html>
