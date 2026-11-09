<?php

declare(strict_types=1);

namespace App\Report;

use App\Model\Entity\Host;

/**
 * Workstations matched by the same host entry.
 */
final readonly class ReportGroup
{
    /**
     * @param \App\Model\Entity\Host|null $host Matching host entry, null for the workstations no entry matches.
     * @param list<\App\Report\HostReport> $reports Reports, sorted by host name.
     */
    public function __construct(
        public ?Host $host,
        public array $reports,
    ) {
    }

    /**
     * Returns the title of the group.
     *
     * @return string
     */
    public function label(): string
    {
        return $this->host === null ? 'Unmatched' : (string)$this->host->get('name');
    }

    /**
     * Returns the main profile of the host entry.
     *
     * @return string|null
     */
    public function profile(): ?string
    {
        return $this->host?->get('main_profile')?->get('id_text');
    }

    /**
     * Returns the most severe status of the group.
     *
     * @return \App\Report\ReportStatus
     */
    public function status(): ReportStatus
    {
        return array_reduce(
            $this->reports,
            fn(ReportStatus $worst, HostReport $report): ReportStatus => $worst->worst($report->status()),
            ReportStatus::Success,
        );
    }

    /**
     * Counts the workstations by status.
     *
     * @return array<string, int> Count by status value, only the statuses present.
     */
    public function countByStatus(): array
    {
        $counts = [];
        foreach ($this->reports as $report) {
            $counts[$report->status()->value] = ($counts[$report->status()->value] ?? 0) + 1;
        }

        return $counts;
    }
}
