<?php

declare(strict_types=1);

namespace App\View;

/**
 * Colour theme chosen by the visitor and kept in the wpkg_theme cookie.
 */
enum Theme: string
{
    public const COOKIE = 'wpkg_theme';

    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';

    /**
     * Reads the theme from a cookie value, falling back to Auto.
     *
     * @param string|null $cookieValue Raw cookie value.
     * @return self
     */
    public static function fromCookie(?string $cookieValue): self
    {
        return self::tryFrom((string)$cookieValue) ?? self::Auto;
    }

    /**
     * Label shown on the selector button.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Auto'),
            self::Light => __('Light'),
            self::Dark => __('Dark'),
        };
    }

    /**
     * Attribute written on the html tag; Auto writes nothing so the system preference decides.
     *
     * @return string
     */
    public function htmlAttribute(): string
    {
        return $this === self::Auto ? '' : ' data-theme="' . $this->value . '"';
    }
}
