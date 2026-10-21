<?php

namespace App\Domain\Widgets;

use App\Models\Branch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cuaca untuk lokasi cabang aktif (Blueprint §5.3) memakai Open-Meteo (tanpa API key).
 * Data terakhir yang berhasil disimpan, sehingga bila pembaruan gagal widget tetap
 * menampilkan data lama beserta penandanya.
 */
class WeatherService
{
    public const CODES = [
        0 => 'Cerah', 1 => 'Cerah berawan', 2 => 'Berawan sebagian', 3 => 'Berawan',
        45 => 'Berkabut', 48 => 'Berkabut', 51 => 'Gerimis ringan', 53 => 'Gerimis', 55 => 'Gerimis lebat',
        61 => 'Hujan ringan', 63 => 'Hujan', 65 => 'Hujan lebat', 80 => 'Hujan lokal', 81 => 'Hujan lokal',
        82 => 'Hujan sangat lebat', 95 => 'Badai petir', 96 => 'Badai petir', 99 => 'Badai petir',
    ];

    /** @return array{status:string, temp?:float, label?:string, updated_at?:string, stale?:bool} */
    public function forBranch(Branch $branch): array
    {
        if (! $branch->hasCoordinates()) {
            return ['status' => 'no_location'];
        }

        $key = "weather:{$branch->tenant_id}:{$branch->id}:".round($branch->latitude, 3).','.round($branch->longitude, 3);

        $fresh = Cache::get($key.':fresh');
        if ($fresh) {
            return $fresh + ['status' => 'ok', 'stale' => false];
        }

        try {
            $res = Http::timeout(4)->retry(1, 200)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'current' => 'temperature_2m,weather_code',
                'timezone' => $branch->timezone,
            ])->throw()->json('current');

            $data = [
                'temp' => round((float) $res['temperature_2m']),
                'label' => self::CODES[(int) $res['weather_code']] ?? 'Tidak diketahui',
                'updated_at' => now()->toIso8601String(),
            ];
            Cache::put($key.':fresh', $data, now()->addMinutes(30));
            Cache::forever($key.':last', $data);

            return $data + ['status' => 'ok', 'stale' => false];
        } catch (\Throwable $e) {
            $last = Cache::get($key.':last');

            return $last ? $last + ['status' => 'ok', 'stale' => true] : ['status' => 'failed'];
        }
    }
}
