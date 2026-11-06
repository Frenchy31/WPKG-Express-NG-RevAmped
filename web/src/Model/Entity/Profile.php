<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Profile record.
 */
class Profile extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'enabled' => true,
        'id_text' => true,
        'notes' => true,
        'packages' => true,
        'profile_dependencies' => true,
    ];
}
