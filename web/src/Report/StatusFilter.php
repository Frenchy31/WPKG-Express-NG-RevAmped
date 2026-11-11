<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Statuses chosen on the dashboard to pick the workstations of the room map.
 */
final readonly class StatusFilter
{
    /**
     * Value of the choice that gathers the workstations that did not report for 24 hours, whatever their status.
     */
    public const string STALE = 'stale';

    /**
     * Choices of the form, in display order.
     */
    public const array CHOICES = [
        ReportStatus::Success->value,
        ReportStatus::Failed->value,
        ReportStatus::Warning->value,
        ReportStatus::Unknown->value,
        self::STALE,
    ];

    /**
     * Choices applied when the form was not submitted.
     */
    public const array DEFAULT = [ReportStatus::Failed->value, ReportStatus::Warning->value];

    /**
     * @param list<string> $selected Checked choices.
     */
    public function __construct(private array $selected)
    {
    }

    /**
     * Reads the filter from the query string.
     *
     * @param array<string, mixed> $query Query parameters.
     * @return self
     */
    public static function fromQuery(array $query): self
    {
        if (!isset($query['filter'])) {
            return new self(self::DEFAULT);
        }
        $requested = array_filter((array)($query['status'] ?? []), 'is_string');

        return new self(array_values(array_intersect(self::CHOICES, $requested)));
    }

    /**
     * Tells whether a choice is checked.
     *
     * @param string $choice One of CHOICES.
     * @return bool
     */
    public function isChecked(string $choice): bool
    {
        return in_array($choice, $this->selected, true);
    }

    /**
     * Picks the workstations to show in each group.
     *
     * @param list<\App\Report\ReportGroup> $groups Groups of the dashboard.
     * @param \DateTimeImmutable $now Current time.
     * @return array<int, list<\App\Report\HostReport>> Reports to show by group position, without empty groups.
     */
    public function visibleReports(array $groups, DateTimeImmutable $now): array
    {
        $visible = [];
        foreach ($groups as $position => $group) {
            $reports = array_values(array_filter(
                $group->reports,
                fn(HostReport $report): bool => $this->matches($report, $now),
            ));
            if ($reports !== []) {
                $visible[$position] = $reports;
            }
        }

        return $visible;
    }

    /**
     * Tells whether a workstation is shown.
     *
     * @param \App\Report\HostReport $report Report.
     * @param \DateTimeImmutable $now Current time.
     * @return bool
     */
    private function matches(HostReport $report, DateTimeImmutable $now): bool
    {
        return $this->isChecked($report->status()->value)
            || ($this->isChecked(self::STALE) && $report->isStale($now));
    }
}
