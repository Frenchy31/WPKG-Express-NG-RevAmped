<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of exit_codes.reboot. The integers are stored in the database.
 */
enum ExitCodeReboot: int
{
    case False = 0;
    case True = 1;
    case Delayed = 2;
    case Postponed = 3;

    /**
     * Returns the value of the `reboot` attribute of an <exit> element, or null when it is omitted.
     *
     * @return string|null
     */
    public function xmlValue(): ?string
    {
        return $this === self::False ? null : strtolower($this->name);
    }

    /**
     * Returns the text shown in the web interface.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::False => __('None'),
            self::True => __('Yes'),
            self::Delayed => __('Delayed'),
            self::Postponed => __('Postponed'),
        };
    }

    /**
     * Finds the case matching an XML attribute value. Unknown or empty values mean no reboot.
     *
     * @param string|null $xmlValue Attribute value.
     * @return self
     */
    public static function fromXml(?string $xmlValue): self
    {
        return match (strtolower((string)$xmlValue)) {
            'true' => self::True,
            'delayed' => self::Delayed,
            'postponed' => self::Postponed,
            default => self::False,
        };
    }
}
