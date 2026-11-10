<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Result of one pass of the reports indexer.
 */
final readonly class IndexState
{
    /**
     * A pass is overdue after this many intervals.
     */
    public const int OVERDUE_AFTER_INTERVALS = 3;

    /**
     * @param \DateTimeImmutable $finishedAt End of the pass.
     * @param int $durationMs Duration of the pass.
     * @param int $filesSeen Report files found in the directory.
     * @param int $filesRead Files read because they were new or changed.
     * @param int $hostsRemoved Workstations whose files disappeared.
     * @param string|null $error Why the pass could not list the directory.
     */
    public function __construct(
        public DateTimeImmutable $finishedAt,
        public int $durationMs,
        public int $filesSeen,
        public int $filesRead,
        public int $hostsRemoved,
        public ?string $error,
    ) {
    }

    /**
     * Builds the state from a row of report_index_state.
     *
     * @param array<string, mixed> $row Row.
     * @return self
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (new DateTimeImmutable())->setTimestamp((int)$row['finished_at']),
            (int)$row['duration_ms'],
            (int)$row['files_seen'],
            (int)$row['files_read'],
            (int)$row['hosts_removed'],
            $row['error'],
        );
    }

    /**
     * Returns the columns of report_index_state, without the key.
     *
     * @return array<string, int|string|null>
     */
    public function toRow(): array
    {
        return [
            'finished_at' => $this->finishedAt->getTimestamp(),
            'duration_ms' => $this->durationMs,
            'files_seen' => $this->filesSeen,
            'files_read' => $this->filesRead,
            'hosts_removed' => $this->hostsRemoved,
            'error' => $this->error,
        ];
    }

    /**
     * Tells whether the indexer stopped or keeps failing.
     *
     * @param \App\Report\IndexState|null $state Last pass, null when none ever ran.
     * @param \DateTimeImmutable $now Current time.
     * @param int $intervalSeconds Interval between two passes.
     * @return bool
     */
    public static function isOverdue(?self $state, DateTimeImmutable $now, int $intervalSeconds): bool
    {
        if ($state === null) {
            return true;
        }

        return $now->getTimestamp() - $state->finishedAt->getTimestamp()
            > self::OVERDUE_AFTER_INTERVALS * $intervalSeconds;
    }
}
