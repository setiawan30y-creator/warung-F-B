<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'legal_name', 'brand_name', 'address', 'city', 'phone', 'whatsapp',
        'email', 'website', 'business_id_number', 'tax_id_number', 'receipt_header', 'receipt_footer',
        'logo_path', 'show_weather_widget', 'show_location_widget'];

    protected function casts(): array
    {
        return ['show_weather_widget' => 'boolean', 'show_location_widget' => 'boolean'];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->brand_name)))
            ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
