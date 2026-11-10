<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Summary of a log, as stored by the reports indexer.
 */
final readonly class IndexedLog implements LogSummary
{
    /**
     * @param \App\Report\ReportStatus $status How the run ended.
     * @param \DateTimeImmutable|null $startedAt When the run started.
     * @param string|null $wpkgVersion Version of wpkg.js.
     * @param list<string> $profiles Profiles applied to the workstation.
     * @param list<string> $succeeded Messages of the actions that succeeded.
     * @param list<string> $errors Messages of the ERROR entries.
     * @param list<string> $warnings Messages of the WARNING entries.
     */
    public function __construct(
        private ReportStatus $status,
        private ?DateTimeImmutable $startedAt,
        private ?string $wpkgVersion,
        private array $profiles,
        private array $succeeded,
        private array $errors,
        private array $warnings,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function status(): ReportStatus
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * @inheritDoc
     */
    public function wpkgVersion(): ?string
    {
        return $this->wpkgVersion;
    }

    /**
     * @inheritDoc
     */
    public function profiles(): array
    {
        return $this->profiles;
    }

    /**
     * @inheritDoc
     */
    public function succeeded(): array
    {
        return $this->succeeded;
    }

    /**
     * @inheritDoc
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @inheritDoc
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
