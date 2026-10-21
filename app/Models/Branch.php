<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const DAYS = [
        'mon' => 'Senin', 'tue' => 'Selasa', 'wed' => 'Rabu', 'thu' => 'Kamis',
        'fri' => 'Jumat', 'sat' => 'Sabtu', 'sun' => 'Minggu',
    ];

    protected $fillable = ['tenant_id', 'code', 'name', 'address', 'city', 'phone', 'email',
        'latitude', 'longitude', 'timezone', 'opening_hours', 'is_active'];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public static function defaultOpeningHours(): array
    {
        return collect(self::DAYS)->mapWithKeys(fn ($l, $d) => [$d => ['open' => '08:00', 'close' => '21:00', 'closed' => false]])->all();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch_access')->withTimestamps();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        if ($this->hasCoordinates()) {
            return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
        }
        if ($this->address) {
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->address.' '.$this->city);
        }

        return null;
    }
}
