<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PackageCheck record.
 */
class PackageCheck extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'type' => true,
        'condition' => true,
        'path' => true,
        'value' => true,
        'parent_id' => true,
    ];
}
