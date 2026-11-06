<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of packages.execute. The integers are stored in the database.
 */
enum PackageExecute: int
{
    case Normal = 0;
    case Always = 1;
    case Once = 2;

    /**
     * Returns the value of the `execute` attribute in packages.xml.
     *
     * @return string
     */
    public function xmlValue(): string
    {
        return strtolower($this->name);
    }
}
