<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PackageAction record.
 */
class PackageAction extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'type' => true,
        'command' => true,
        'timeout' => true,
        'workdir' => true,
    ];
}
