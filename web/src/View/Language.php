<?php

declare(strict_types=1);

namespace App\View;

/**
 * Interface language chosen by the visitor and kept in the wpkg_lang cookie.
 */
enum Language: string
{
    public const COOKIE = 'wpkg_lang';

    case English = 'en';
    case French = 'fr';

    /**
     * Reads the language from a cookie value; null when absent or unknown.
     *
     * @param string|null $cookieValue Raw cookie value.
     * @return self|null
     */
    public static function fromCookie(?string $cookieValue): ?self
    {
        return self::tryFrom((string)$cookieValue);
    }

    /**
     * Finds the language of a locale such as fr_FR; null when the locale is not shipped.
     *
     * @param string $locale Locale identifier.
     * @return self|null
     */
    public static function fromLocale(string $locale): ?self
    {
        return self::tryFrom(strtolower(substr($locale, 0, 2)));
    }

    /**
     * Locale given to I18n.
     *
     * @return string
     */
    public function locale(): string
    {
        return match ($this) {
            self::English => 'en_US',
            self::French => 'fr_FR',
        };
    }

    /**
     * Name of the language in that language, shown on the selector button.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'Français',
        };
    }

    /**
     * Format of a time of day: 12 hours in English, 24 hours in French.
     *
     * @return string
     */
    public function timeFormat(): string
    {
        return match ($this) {
            self::English => 'h:i A',
            self::French => 'H:i',
        };
    }

    /**
     * Format of the timestamps: 12 hours in English, 24 hours in French.
     *
     * @return string
     */
    public function dateFormat(): string
    {
        return match ($this) {
            self::English => 'Y-m-d h:i:s A',
            self::French => 'Y-m-d H:i:s',
        };
    }
}
