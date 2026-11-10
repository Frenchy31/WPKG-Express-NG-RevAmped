<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * What the pages show of a run: read from the log file, or from the index.
 */
interface LogSummary
{
    /**
     * Tells how the run ended.
     *
     * @return \App\Report\ReportStatus
     */
    public function status(): ReportStatus;

    /**
     * Returns when the run started.
     *
     * @return \DateTimeImmutable|null
     */
    public function startedAt(): ?DateTimeImmutable;

    /**
     * Returns the version of wpkg.js that wrote the log.
     *
     * @return string|null
     */
    public function wpkgVersion(): ?string;

    /**
     * Returns the profiles applied to the workstation.
     *
     * @return list<string>
     */
    public function profiles(): array;

    /**
     * Returns the messages of the package actions that succeeded.
     *
     * @return list<string>
     */
    public function succeeded(): array;

    /**
     * Returns the messages of the ERROR entries.
     *
     * @return list<string>
     */
    public function errors(): array;

    /**
     * Returns the messages of the WARNING entries.
     *
     * @return list<string>
     */
    public function warnings(): array;
}
