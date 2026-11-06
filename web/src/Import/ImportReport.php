<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Outcome of one uploaded file.
 */
class ImportReport
{
    /**
     * @var array<string>
     */
    public array $errors = [];

    /**
     * @var array<string>
     */
    public array $warnings = [];

    /**
     * @var array<string>
     */
    public array $notices = [];

    public int $saved = 0;

    public int $total = 0;

    /**
     * Tells whether the file failed schema validation or could not be parsed.
     */
    public bool $rejected = false;
}
