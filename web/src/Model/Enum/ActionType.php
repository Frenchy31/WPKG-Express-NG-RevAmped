<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of package_actions.type. The integers are stored in the database.
 */
enum ActionType: int
{
    case Install = 0;
    case Upgrade = 1;
    case Downgrade = 2;
    case Remove = 3;

    /**
     * Returns the element name in packages.xml.
     *
     * @return string
     */
    public function xmlElement(): string
    {
        return strtolower($this->name);
    }
}
