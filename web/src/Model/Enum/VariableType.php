<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of variables.ref_type. The integers are stored in the database.
 */
enum VariableType: int
{
    case Package = 0;
    case Profile = 1;
    case Host = 2;

    /**
     * Returns the name of the table owning the variable.
     *
     * @return string
     */
    public function tableName(): string
    {
        return $this->name . 's';
    }

    /**
     * Finds the case matching a URL segment such as `host`.
     *
     * @param string $slug URL segment.
     * @return self|null
     */
    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if (strtolower($case->name) === strtolower($slug)) {
                return $case;
            }
        }

        return null;
    }
}
