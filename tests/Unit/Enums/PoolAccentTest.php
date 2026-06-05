<?php

namespace Tests\Unit\Enums;

use App\Enums\PoolAccent;
use Tests\TestCase;

class PoolAccentTest extends TestCase
{
    /**
     * The eyebrow ink tints small text on a white email body, so every accent must clear WCAG AA
     * (4.5:1) against white — otherwise a light accent (gold, teal) renders an illegible eyebrow.
     */
    public function test_every_eyebrow_ink_clears_wcag_aa_contrast_on_white(): void
    {
        foreach (PoolAccent::cases() as $accent) {
            $ratio = $this->contrastWithWhite($accent->eyebrowInk());

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                "PoolAccent::{$accent->name} eyebrow ink {$accent->eyebrowInk()} only reaches "
                    .round($ratio, 2).':1 on white (needs >= 4.5:1).',
            );
        }
    }

    /**
     * The enum hardcodes the gradient hex used to render emails; the in-app UI uses the same
     * colours from `resources/css/app.css` (the `--g-*` custom properties). Nothing links the two,
     * so this guards them from silently drifting apart.
     */
    public function test_gradient_hex_matches_the_css_custom_properties(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        $vars = [
            'pitch' => PoolAccent::Pitch,
            'teal' => PoolAccent::Teal,
            'gold' => PoolAccent::Gold,
            'violet' => PoolAccent::Violet,
        ];

        foreach ($vars as $name => $accent) {
            preg_match(
                "/--g-{$name}:\\s*linear-gradient\\(135deg,\\s*(#[0-9a-fA-F]{6})\\s*0%,\\s*(#[0-9a-fA-F]{6})\\s*100%\\)/",
                (string) $css,
                $matches,
            );

            $this->assertNotEmpty($matches, "Could not find --g-{$name} in resources/css/app.css.");
            $this->assertSame(strtolower($accent->gradientFrom()), strtolower($matches[1]), "PoolAccent::{$accent->name} 'from' hex drifted from --g-{$name}.");
            $this->assertSame(strtolower($accent->gradientTo()), strtolower($matches[2]), "PoolAccent::{$accent->name} 'to' hex drifted from --g-{$name}.");
        }
    }

    private function contrastWithWhite(string $hex): float
    {
        return 1.05 / ($this->relativeLuminance($hex) + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
