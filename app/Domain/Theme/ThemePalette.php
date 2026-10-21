<?php

namespace App\Domain\Theme;

use App\Models\ThemeSetting;

/**
 * Preset warna, kunci token, dan pemeriksaan kontras WCAG (Blueprint §5.2).
 */
class ThemePalette
{
    public const KEYS = [
        'primary' => 'Warna utama',
        'on_primary' => 'Teks di atas warna utama',
        'accent' => 'Aksen',
        'background' => 'Latar',
        'surface' => 'Permukaan (kartu)',
        'text' => 'Teks',
        'muted' => 'Teks sekunder',
        'border' => 'Garis tepi',
        'success' => 'Status sukses',
        'warning' => 'Status peringatan',
        'danger' => 'Status bahaya',
        'info' => 'Status informasi',
    ];

    public const FONTS = [
        'system' => ['label' => 'Bawaan sistem', 'stack' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'google' => null],
        'jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif', 'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700'],
        'inter' => ['label' => 'Inter', 'stack' => 'Inter, ui-sans-serif, system-ui, sans-serif', 'google' => 'Inter:wght@400;500;600;700'],
        'nunito' => ['label' => 'Nunito', 'stack' => 'Nunito, ui-sans-serif, system-ui, sans-serif', 'google' => 'Nunito:wght@400;500;600;700'],
    ];

    public const RADII = ['sm' => '6px', 'md' => '10px', 'lg' => '16px'];

    public static function presets(): array
    {
        return [
            'almara' => ['label' => 'Daun pisang',
                'light' => ['primary' => '#1F5C3A', 'on_primary' => '#FFFFFF', 'accent' => '#C2361F', 'background' => '#F3F4F1', 'surface' => '#FFFFFF', 'text' => '#18201B', 'muted' => '#5A645D', 'border' => '#DCE0DA', 'success' => '#1E7A4C', 'warning' => '#9A5B00', 'danger' => '#B42318', 'info' => '#1D5FA8'],
                'dark' => ['primary' => '#6CC392', 'on_primary' => '#0C1A12', 'accent' => '#F07A62', 'background' => '#101511', 'surface' => '#19201B', 'text' => '#E9EFEA', 'muted' => '#9EAAA2', 'border' => '#2B352E', 'success' => '#4CC38A', 'warning' => '#F0B04A', 'danger' => '#F97066', 'info' => '#6AA9F0']],
            'cabai' => ['label' => 'Cabai',
                'light' => ['primary' => '#B42A1A', 'on_primary' => '#FFFFFF', 'accent' => '#1F5C3A', 'background' => '#F6F4F3', 'surface' => '#FFFFFF', 'text' => '#221817', 'muted' => '#6A5E5C', 'border' => '#E5DDDB', 'success' => '#1E7A4C', 'warning' => '#9A5B00', 'danger' => '#B42318', 'info' => '#1D5FA8'],
                'dark' => ['primary' => '#F2806C', 'on_primary' => '#1C0D0A', 'accent' => '#6CC392', 'background' => '#151110', 'surface' => '#201A19', 'text' => '#F3ECEA', 'muted' => '#ADA19E', 'border' => '#382F2D', 'success' => '#4CC38A', 'warning' => '#F0B04A', 'danger' => '#F97066', 'info' => '#6AA9F0']],
            'kopi' => ['label' => 'Kopi',
                'light' => ['primary' => '#6F4E37', 'on_primary' => '#FFFFFF', 'accent' => '#C08B3E', 'background' => '#F5F1EC', 'surface' => '#FFFFFF', 'text' => '#2A1F18', 'muted' => '#6E6158', 'border' => '#E2D8CE', 'success' => '#1E7A4C', 'warning' => '#A15C00', 'danger' => '#B42318', 'info' => '#1D5FA8'],
                'dark' => ['primary' => '#C49A75', 'on_primary' => '#1B130E', 'accent' => '#E2B567', 'background' => '#14100D', 'surface' => '#1F1915', 'text' => '#F2EBE4', 'muted' => '#ABA096', 'border' => '#352C26', 'success' => '#4CC38A', 'warning' => '#F0B04A', 'danger' => '#F97066', 'info' => '#6AA9F0']],
            'laut' => ['label' => 'Laut',
                'light' => ['primary' => '#1D5FA8', 'on_primary' => '#FFFFFF', 'accent' => '#D97706', 'background' => '#F3F6FA', 'surface' => '#FFFFFF', 'text' => '#152033', 'muted' => '#5B6678', 'border' => '#DAE1EA', 'success' => '#1E7A4C', 'warning' => '#A15C00', 'danger' => '#B42318', 'info' => '#1D5FA8'],
                'dark' => ['primary' => '#6AA9F0', 'on_primary' => '#0B1524', 'accent' => '#FBBF24', 'background' => '#0E1320', 'surface' => '#171E2D', 'text' => '#E7EDF6', 'muted' => '#9AA6B8', 'border' => '#2A3346', 'success' => '#4CC38A', 'warning' => '#F0B04A', 'danger' => '#F97066', 'info' => '#6AA9F0']],
            'grafit' => ['label' => 'Grafit',
                'light' => ['primary' => '#27272A', 'on_primary' => '#FFFFFF', 'accent' => '#B4532A', 'background' => '#F6F6F5', 'surface' => '#FFFFFF', 'text' => '#18181B', 'muted' => '#62626A', 'border' => '#E1E1E0', 'success' => '#1E7A4C', 'warning' => '#A15C00', 'danger' => '#B42318', 'info' => '#1D5FA8'],
                'dark' => ['primary' => '#F4F4F5', 'on_primary' => '#18181B', 'accent' => '#E07A4F', 'background' => '#111113', 'surface' => '#1C1C1F', 'text' => '#F4F4F5', 'muted' => '#A1A1AA', 'border' => '#303036', 'success' => '#4CC38A', 'warning' => '#F0B04A', 'danger' => '#F97066', 'info' => '#6AA9F0']],
        ];
    }

    public static function defaults(): array
    {
        $p = self::presets()['almara'];

        return ['mode' => 'system', 'preset' => 'almara', 'font' => 'jakarta', 'radius' => 'md',
            'colors_light' => $p['light'], 'colors_dark' => $p['dark']];
    }

    /** Rasio kontras WCAG 2.x antara dua warna HEX. */
    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        [$hi, $lo] = $l1 > $l2 ? [$l1, $l2] : [$l2, $l1];

        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }

    protected static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }
        $rgb = array_map(fn ($c) => hexdec($c) / 255, str_split(substr($hex, 0, 6), 2));
        $lin = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $rgb);

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    /** Daftar pasangan warna yang kontrasnya di bawah 4.5:1. */
    public static function contrastIssues(array $c): array
    {
        $pairs = [
            ['text', 'background', 'Teks pada latar'],
            ['text', 'surface', 'Teks pada kartu'],
            ['on_primary', 'primary', 'Teks tombol utama'],
            ['muted', 'surface', 'Teks sekunder pada kartu'],
        ];
        $issues = [];
        foreach ($pairs as [$fg, $bg, $label]) {
            if (! self::isHex($c[$fg] ?? null) || ! self::isHex($c[$bg] ?? null)) {
                continue;
            }
            $ratio = self::contrast($c[$fg], $c[$bg]);
            if ($ratio < 4.5) {
                $issues[] = ['label' => $label, 'ratio' => $ratio];
            }
        }

        return $issues;
    }

    public static function isHex(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $v);
    }

    /** Ganti nilai yang tidak valid dengan nilai cadangan (untuk pratinjau yang aman). */
    public static function sanitize(array $colors, array $fallback): array
    {
        $out = [];
        foreach (array_keys(self::KEYS) as $k) {
            $out[$k] = self::isHex($colors[$k] ?? null) ? $colors[$k] : $fallback[$k];
        }

        return $out;
    }

    /** CSS variabel untuk dimuat di <head>. Data-theme mengikuti pilihan mode. */
    public static function css(?ThemeSetting $theme): string
    {
        $t = $theme ? $theme->only(['font', 'radius', 'colors_light', 'colors_dark']) : self::defaults();
        $font = self::FONTS[$t['font']]['stack'] ?? self::FONTS['system']['stack'];
        $radius = self::RADII[$t['radius']] ?? self::RADII['md'];
        $vars = fn (array $c) => collect($c)->only(array_keys(self::KEYS))
            ->filter(fn ($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v))
            ->map(fn ($v, $k) => '--c-'.str_replace('_', '-', $k).':'.$v.';')->implode('');

        $light = $vars($t['colors_light']);
        $dark = $vars($t['colors_dark']);

        return ":root{--font:{$font};--radius:{$radius};{$light}color-scheme:light}"
            ."@media (prefers-color-scheme: dark){:root:not([data-theme=\"light\"]){{$dark}color-scheme:dark}}"
            .":root[data-theme=\"dark\"]{{$dark}color-scheme:dark}";
    }
}
