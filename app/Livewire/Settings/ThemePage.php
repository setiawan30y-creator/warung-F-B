<?php

namespace App\Livewire\Settings;

use App\Domain\Theme\ThemePalette;
use App\Livewire\Concerns\TenantPage;
use App\Models\ThemeSetting;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tema')]
class ThemePage extends Component
{
    use TenantPage;

    public string $mode = 'system';

    public string $preset = 'almara';

    public string $font = 'jakarta';

    public string $radius = 'md';

    public array $light = [];

    public array $dark = [];

    public string $editing = 'light';

    public function mount(): void
    {
        $this->authorize('theme.manage');
        $t = $this->setting();
        $this->mode = $t->mode;
        $this->preset = $t->preset;
        $this->font = $t->font;
        $this->radius = $t->radius;
        $this->light = $t->colors_light;
        $this->dark = $t->colors_dark;
    }

    protected function setting(): ThemeSetting
    {
        return ThemeSetting::firstOrCreate([], ThemePalette::defaults());
    }

    public function applyPreset(string $key): void
    {
        $preset = ThemePalette::presets()[$key] ?? null;
        if (! $preset) {
            return;
        }
        $this->preset = $key;
        $this->light = $preset['light'];
        $this->dark = $preset['dark'];
    }

    public function resetDefaults(): void
    {
        $d = ThemePalette::defaults();
        $this->mode = $d['mode'];
        $this->font = $d['font'];
        $this->radius = $d['radius'];
        $this->applyPreset($d['preset']);
    }

    protected function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $rules = [
            'mode' => 'required|in:light,dark,system',
            'font' => 'required|in:'.implode(',', array_keys(ThemePalette::FONTS)),
            'radius' => 'required|in:'.implode(',', array_keys(ThemePalette::RADII)),
        ];
        foreach (array_keys(ThemePalette::KEYS) as $k) {
            $rules["light.$k"] = $hex;
            $rules["dark.$k"] = $hex;
        }

        return $rules;
    }

    protected function messages(): array
    {
        return ['light.*.regex' => 'Gunakan format HEX 6 digit, contoh #1F5C3A.', 'dark.*.regex' => 'Gunakan format HEX 6 digit, contoh #1F5C3A.'];
    }

    public function save(): void
    {
        $this->authorize('theme.manage');
        $this->validate();
        $isPreset = (ThemePalette::presets()[$this->preset]['light'] ?? null) === $this->light
            && (ThemePalette::presets()[$this->preset]['dark'] ?? null) === $this->dark;

        $this->setting()->update([
            'mode' => $this->mode,
            'preset' => $isPreset ? $this->preset : 'custom',
            'font' => $this->font,
            'radius' => $this->radius,
            'colors_light' => $this->light,
            'colors_dark' => $this->dark,
        ]);
        $this->redirectRoute('settings.theme', navigate: false);
        session()->flash('status', 'Tema disimpan dan diterapkan untuk semua pengguna usaha ini.');
    }

    public function render()
    {
        $fallback = ThemePalette::presets()['almara'];
        $palette = $this->editing === 'dark'
            ? ThemePalette::sanitize($this->dark, $fallback['dark'])
            : ThemePalette::sanitize($this->light, $fallback['light']);

        return view('livewire.settings.theme', [
            'presets' => ThemePalette::presets(),
            'keys' => ThemePalette::KEYS,
            'issuesLight' => ThemePalette::contrastIssues($this->light),
            'issuesDark' => ThemePalette::contrastIssues($this->dark),
            'palette' => $palette,
        ]);
    }
}
