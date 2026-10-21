<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    @livewireStyles
</head>
<body x-data="{ nav: false, online: navigator.onLine }"
      x-on:online.window="online = true" x-on:offline.window="online = false">
<div class="net-banner" x-show="!online" x-cloak role="status">
    Koneksi terputus. Perubahan belum bisa disimpan sampai koneksi kembali.
</div>

<div class="shell">
    <aside class="sidebar" :class="nav && 'open'" aria-label="Navigasi utama">
        @php $profile = $currentTenant->companyProfile; $user = auth()->user(); @endphp
        <div class="ticket">
            <div class="ticket-brand">
                <div class="ticket-logo">
                    @if ($profile?->logoUrl())
                        <img src="{{ $profile->logoUrl() }}" alt="Logo {{ $profile->brand_name }}">
                    @else
                        {{ $profile?->initials() }}
                    @endif
                </div>
                <strong>{{ $profile->brand_name ?? $currentTenant->name }}</strong>
            </div>
            <hr class="ticket-rule">
            @php $branches = $user->accessibleBranches()->get(['id', 'name', 'code']); @endphp
            <small>Cabang aktif</small>
            @if ($branches->count() > 1)
                <form method="POST" action="{{ route('branch.switch') }}">
                    @csrf
                    <label class="sr-only" for="branch-switch">Ganti cabang</label>
                    <select id="branch-switch" name="branch_id" onchange="this.form.submit()">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($currentBranch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <div class="branch-name">{{ $currentBranch->name ?? 'Belum ada cabang' }}</div>
            @endif
        </div>

        <nav class="nav">
            <a href="{{ route('dashboard') }}" wire:navigate @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <x-icon name="home"/> Beranda
            </a>

            <div class="nav-group">Operasional</div>
            <span class="disabled"><x-icon name="bowl"/> Menu & resep <span class="soon">Fase 2</span></span>
            <span class="disabled"><x-icon name="box"/> Stok & pembelian <span class="soon">Fase 2</span></span>
            <span class="disabled"><x-icon name="cash"/> Kasir & pesanan <span class="soon">Fase 3</span></span>
            <span class="disabled"><x-icon name="chef"/> Layar dapur <span class="soon">Fase 4</span></span>
            <span class="disabled"><x-icon name="clock"/> Karyawan & shift <span class="soon">Fase 6</span></span>

            @php
                $settings = [
                    ['settings.company', 'company.manage', 'building', 'Profil perusahaan'],
                    ['settings.branches', 'branches.view', 'store', 'Cabang'],
                    ['settings.users', 'users.view', 'users', 'Pengguna'],
                    ['settings.roles', 'roles.manage', 'shield', 'Peran & izin'],
                    ['settings.theme', 'theme.manage', 'palette', 'Tema'],
                    ['settings.audit', 'audit.view', 'log', 'Log audit'],
                ];
                $visible = collect($settings)->filter(fn ($s) => $user->can($s[1]));
            @endphp
            @if ($visible->isNotEmpty())
                <div class="nav-group">Pengaturan</div>
                @foreach ($visible as [$route, $perm, $icon, $label])
                    <a href="{{ route($route) }}" wire:navigate @if(request()->routeIs($route)) aria-current="page" @endif>
                        <x-icon :name="$icon"/> {{ $label }}
                    </a>
                @endforeach
            @endif
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" type="button" x-on:click="nav = !nav" aria-label="Buka menu">
                <x-icon name="menu"/>
            </button>
            <h1 class="small" style="font-size:15px">{{ $title ?? '' }}</h1>
            <div class="spacer"></div>
            <button class="icon-btn" type="button" aria-label="Ganti mode terang/gelap"
                    x-data x-on:click="
                        const el = document.documentElement;
                        const dark = el.getAttribute('data-theme') === 'dark' || (!el.getAttribute('data-theme') && matchMedia('(prefers-color-scheme: dark)').matches);
                        const next = dark ? 'light' : 'dark';
                        el.setAttribute('data-theme', next);
                        fetch('{{ route('theme-mode') }}', {method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({mode: next})});
                    ">
                <x-icon name="moon"/>
            </button>
            <a class="btn btn-ghost btn-sm" href="{{ route('account') }}" wire:navigate>
                <x-icon name="user" style="width:16px;height:16px"/> {{ $user->name }}
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="icon-btn" type="submit" aria-label="Keluar" title="Keluar"><x-icon name="logout"/></button>
            </form>
        </header>

        <main class="content" x-on:click="nav = false">
            @if (session('status'))
                <div class="alert ok" style="margin-bottom:18px" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>

        <footer class="footer-sign">ALMARA Restaurant &amp; Beverage OS, dikembangkan oleh @2026-Alifiandra</footer>
    </div>
</div>

<div x-data="{ show: false, msg: '' }"
     x-on:toast.window="msg = $event.detail.message ?? $event.detail[0]?.message ?? ''; show = true; clearTimeout(window.__t); window.__t = setTimeout(() => show = false, 3200)"
     x-show="show" x-transition.opacity x-cloak class="toast" role="status" x-text="msg"></div>

@livewireScripts
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
</body>
</html>
