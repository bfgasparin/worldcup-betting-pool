<?php

namespace App\Enums;

/**
 * The colour identity given to a game so that games played over the *same* tournament — which
 * otherwise share a name, dates and sport — read as distinct at a glance, everywhere the game is
 * shown. This enum is the server-side source of truth for the gradient colours: emails render the
 * raw hex/gradient from here, while the in-app UI carries the same keys as Tailwind classes.
 *
 * Keep the keys and colours in sync with the kit definitions in `resources/js/lib/accents.ts`
 * (and the `--g-*` gradients / `.kit-rail-*` utilities in `resources/css/app.css`).
 */
enum GameAccent: string
{
    case Pitch = 'pitch';
    case Teal = 'teal';
    case Gold = 'gold';
    case Violet = 'violet';

    /**
     * The accent for a game at the given 0-based position among its tournament's games, cycling
     * the cases. Mirrors `gameAccent()` in `resources/js/lib/accents.ts`, and is used to assign a
     * distinct default accent to each game a factory builds.
     */
    public static function forIndex(int $index): self
    {
        $cases = self::cases();
        $count = count($cases);

        return $cases[(($index % $count) + $count) % $count];
    }

    /** The lighter gradient stop — also the bulletproof solid `bgcolor` fallback for Outlook. */
    public function gradientFrom(): string
    {
        return match ($this) {
            self::Pitch => '#16C07A',
            self::Teal => '#4CC6DA',
            self::Gold => '#FFD15C',
            self::Violet => '#9A8CFF',
        };
    }

    /** The darker gradient stop. */
    public function gradientTo(): string
    {
        return match ($this) {
            self::Pitch => '#0A6B49',
            self::Teal => '#1D8AA6',
            self::Gold => '#FF9F1C',
            self::Violet => '#6A5FD6',
        };
    }

    /** The full CSS gradient, for an email `background-image`. */
    public function gradientCss(): string
    {
        return "linear-gradient(135deg, {$this->gradientFrom()} 0%, {$this->gradientTo()} 100%)";
    }

    /** A single solid colour (the lighter stop), for an email `bgcolor` fallback. */
    public function solidHex(): string
    {
        return $this->gradientFrom();
    }

    /** The text colour that reads on the gradient — ink for gold, white otherwise. */
    public function onColor(): string
    {
        return match ($this) {
            self::Gold => '#3A2600',
            default => '#FFFFFF',
        };
    }

    /**
     * A darkened accent for tinting small text on a white email body. Unlike {@see gradientTo()},
     * each value clears WCAG AA contrast (>= 4.5:1) on white, so the gradient's lighter stops
     * (gold and teal especially) never produce an illegible eyebrow. Guarded by GameAccentTest.
     */
    public function eyebrowInk(): string
    {
        return match ($this) {
            self::Pitch => '#0A6B49',
            self::Teal => '#136B80',
            self::Gold => '#8A5A00',
            self::Violet => '#5B4ECC',
        };
    }
}
