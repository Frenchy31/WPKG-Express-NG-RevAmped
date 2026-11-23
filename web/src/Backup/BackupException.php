<?php

declare(strict_types=1);

namespace App\Backup;

use RuntimeException;

/**
 * A backup operation was refused or failed. The message is translated and meant for the administrator.
 */
class BackupException extends RuntimeException
{
}
