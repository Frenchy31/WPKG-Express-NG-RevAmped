<?php

declare(strict_types=1);

namespace App\Cleanup;

use RuntimeException;

/**
 * The cleanup failed and was rolled back. The message is translated and meant for the administrator.
 */
class CleanupException extends RuntimeException
{
}
