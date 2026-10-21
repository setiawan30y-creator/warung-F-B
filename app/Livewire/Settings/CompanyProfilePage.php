<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\TenantPage;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profil perusahaan')]
class CompanyProfilePage extends Component
{
    use TenantPage, WithFileUploads;

    public string $brand_name = '';

    public ?string $legal_name = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $phone = null;

    public ?string $whatsapp = null;

    public ?string $email = null;

    public ?string $website = null;

    public ?string $business_id_number = null;

    public ?string $tax_id_number = null;

    public ?string $receipt_header = null;

    public ?string $receipt_footer = null;

    public bool $show_weather_widget = true;

    public bool $show_location_widget = true;

    /** Logo baru (sementara, untuk pratinjau sebelum disimpan). */
    #[Validate('nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000')]
    public $logo;

    public function mount(): void
    {
        $this->authorize('company.manage');
        $this->fill($this->profile()->only([
            'brand_name', 'legal_name', 'address', 'city', 'phone', 'whatsapp', 'email', 'website',
            'business_id_number', 'tax_id_number', 'receipt_header', 'receipt_footer',
            'show_weather_widget', 'show_location_widget',
        ]));
    }

    protected function profile(): CompanyProfile
    {
        return CompanyProfile::firstOrCreate([], ['brand_name' => $this->tenant()->name]);
    }

    protected function rules(): array
    {
        return [
            'brand_name' => 'required|string|max:120',
            'legal_name' => 'nullable|string|max:160',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => 'nullable|email|max:160',
            'website' => 'nullable|url|max:160',
            'business_id_number' => 'nullable|string|max:50',
            'tax_id_number' => 'nullable|string|max:50',
            'receipt_header' => 'nullable|string|max:255',
            'receipt_footer' => 'nullable|string|max:255',
            'show_weather_widget' => 'boolean',
            'show_location_widget' => 'boolean',
        ];
    }

    protected function validationAttributes(): array
    {
        return ['brand_name' => 'nama usaha', 'whatsapp' => 'nomor WhatsApp', 'phone' => 'nomor telepon', 'logo' => 'logo'];
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    public function save(): void
    {
        $this->authorize('company.manage');
        $data = $this->validate();
        unset($data['logo']);
        $this->profile()->update($data);
        $this->dispatch('toast', message: 'Profil perusahaan disimpan.');
    }

    public function saveLogo(): void
    {
        $this->authorize('company.manage');
        $this->validateOnly('logo');
        if (! $this->logo) {
            return;
        }

        $profile = $this->profile();
        $old = $profile->logo_path;
        // Disimpan dalam folder per tenant agar terisolasi.
        $path = $this->logo->storeAs(
            'tenants/'.$this->tenant()->uuid.'/branding',
            'logo-'.Str::random(12).'.'.$this->logo->getClientOriginalExtension(),
            'public'
        );
        $profile->update(['logo_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
        $this->reset('logo');
        $this->dispatch('toast', message: 'Logo disimpan.');
    }

    public function removeLogo(): void
    {
        $this->authorize('company.manage');
        $profile = $this->profile();
        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }
        $this->dispatch('toast', message: 'Logo dihapus.');
    }

    public function render()
    {
        return view('livewire.settings.company-profile', ['profile' => $this->profile()]);
    }
}
