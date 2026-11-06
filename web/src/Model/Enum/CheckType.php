<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of package_checks.type. The integers are stored in the database.
 */
enum CheckType: int
{
    case Logical = 0;
    case Registry = 1;
    case File = 2;
    case Uninstall = 3;
    case Execute = 4;
    case Host = 5;

    /**
     * Returns the value of the `type` attribute in packages.xml.
     *
     * @return string
     */
    public function xmlValue(): string
    {
        return strtolower($this->name);
    }

    /**
     * Returns the conditions allowed for this type.
     *
     * @return array<\App\Model\Enum\CheckCondition>
     */
    public function conditions(): array
    {
        return array_values(array_filter(
            CheckCondition::cases(),
            fn(CheckCondition $condition): bool => $condition->type() === $this,
        ));
    }

    /**
     * Finds the case matching an XML attribute value.
     *
     * @param string $xmlValue Attribute value.
     * @return self|null
     */
    public static function fromXml(string $xmlValue): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->xmlValue() === strtolower($xmlValue)) {
                return $case;
            }
        }

        return null;
    }
}
