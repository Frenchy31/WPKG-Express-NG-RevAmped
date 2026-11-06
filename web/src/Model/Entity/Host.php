<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Host record.
 */
class Host extends Entity
{
    /**
     * Fields assignable from request data.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'enabled' => true,
        'name' => true,
        'notes' => true,
        'mainprofile_id' => true,
        'profiles' => true,
    ];
}
