<?php

namespace Tests\Unit;

use App\Domain\Theme\ThemePalette;
use App\Models\ThemeSetting;
use PHPUnit\Framework\TestCase;

class ThemePaletteTest extends TestCase
{
    public function test_contrast_ratio_matches_wcag(): void
    {
        $this->assertSame(21.0, ThemePalette::contrast('#000000', '#FFFFFF'));
        $this->assertSame(1.0, ThemePalette::contrast('#777777', '#777777'));
    }

    public function test_all_presets_meet_minimum_contrast(): void
    {
        foreach (ThemePalette::presets() as $key => $p) {
            $this->assertSame([], ThemePalette::contrastIssues($p['light']), "Preset $key terang");
            $this->assertSame([], ThemePalette::contrastIssues($p['dark']), "Preset $key gelap");
        }
    }

    public function test_css_ignores_invalid_values(): void
    {
        $d = ThemePalette::defaults();
        $d['colors_light']['primary'] = 'red;}body{display:none';
        $theme = new ThemeSetting($d);
        $css = ThemePalette::css($theme);

        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('--c-text:', $css);
    }
}
