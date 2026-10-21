<?php

if (! function_exists('rupiah')) {
    /** Format Rupiah: rupiah(12500) → "Rp12.500". */
    function rupiah(float|int|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }
        $neg = $value < 0;

        return ($neg ? '-' : '').'Rp'.number_format(abs($value), $decimals, ',', '.');
    }
}

if (! function_exists('qty')) {
    /** Format kuantitas tanpa nol berlebih: qty(1250.5) → "1.250,5". */
    function qty(float|int|null $value, int $max = 2): string
    {
        if ($value === null) {
            return '—';
        }
        $s = number_format((float) $value, $max, ',', '.');

        return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
    }
}
