<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * One entry of a wpkg.js log file.
 */
final readonly class LogEntry
{
    /**
     * @param \DateTimeImmutable|null $time Time written by the client, null on a line without prefix.
     * @param string|null $level ERROR, WARNING, INFO or DEBUG, null on a line without prefix.
     * @param string $message Message, with its line breaks restored.
     */
    public function __construct(
        public ?DateTimeImmutable $time,
        public ?string $level,
        public string $message,
    ) {
    }

    /**
     * Returns the CSS class carrying the color of the level.
     *
     * @return string
     */
    public function cssClass(): string
    {
        return 'level-' . strtolower($this->level ?? 'raw');
    }
}
