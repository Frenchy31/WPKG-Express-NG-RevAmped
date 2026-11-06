<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of packages.reboot. The integers are stored in the database.
 */
enum PackageReboot: int
{
    case False = 0;
    case True = 1;
    case Postponed = 2;

    /**
     * Returns the value of the `reboot` attribute in packages.xml.
     *
     * @return string
     */
    public function xmlValue(): string
    {
        return strtolower($this->name);
    }

    /**
     * Returns the text shown in the web interface.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::False => 'No',
            self::True => 'Yes',
            self::Postponed => 'Postponed',
        };
    }
}
