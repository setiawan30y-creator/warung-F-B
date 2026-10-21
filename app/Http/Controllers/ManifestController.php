<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Manifest PWA, memakai nama & warna tenant bila pengguna sudah login. */
class ManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = $user?->tenant()->with(['companyProfile', 'themeSetting'])->first();
        $name = $tenant?->companyProfile?->brand_name ?? config('app.name');
        $color = $tenant?->themeSetting?->colors_light['primary'] ?? '#B4532A';
        $bg = $tenant?->themeSetting?->colors_light['background'] ?? '#F7F4EF';

        return response()->json([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $bg,
            'theme_color' => $color,
            'lang' => 'id',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
