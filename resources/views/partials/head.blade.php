@php
    $tenantForHead = $currentTenant ?? null;
    $theme = $tenantForHead?->themeSetting;
    $fontKey = $theme->font ?? \App\Domain\Theme\ThemePalette::defaults()['font'];
    $font = \App\Domain\Theme\ThemePalette::FONTS[$fontKey] ?? null;
    $mode = auth()->user()?->theme_mode ?? $theme->mode ?? 'system';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $theme->colors_light['primary'] ?? '#1F5C3A' }}">
<meta name="author" content="@2026-Alifiandra">
<title>{{ isset($title) ? $title.' — ' : '' }}{{ $tenantForHead?->companyProfile?->brand_name ?? config('app.name') }}</title>
<link rel="manifest" href="{{ route('manifest') }}">
<link rel="icon" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
@if ($font && $font['google'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ $font['google'] }}&display=swap">
@endif
<style id="tenant-theme">{!! \App\Domain\Theme\ThemePalette::css($theme) !!}</style>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<script>
    (function () {
        var mode = @json($mode);
        if (mode === 'light' || mode === 'dark') document.documentElement.setAttribute('data-theme', mode);
    })();
</script>
