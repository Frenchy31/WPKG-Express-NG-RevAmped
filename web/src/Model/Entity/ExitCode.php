<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ExitCode record.
 */
class ExitCode extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'code' => true,
        'reboot' => true,
    ];
}
