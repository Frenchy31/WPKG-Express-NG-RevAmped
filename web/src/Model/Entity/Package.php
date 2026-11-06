<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Package record.
 */
class Package extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'id_text' => true,
        'enabled' => true,
        'revision' => true,
        'priority' => true,
        'reboot' => true,
        'execute' => true,
        'notify' => true,
        'notes' => true,
        'package_dependencies' => true,
    ];
}
