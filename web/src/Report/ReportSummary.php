<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Totals of the last runs of the fleet, and the workstations to look at first.
 */
final class ReportSummary
{
    /**
     * Rows of the "Needs attention" list.
     */
    public const int ATTENTION_LIMIT = 10;

    /**
     * Longest cause shown, in characters.
     */
    public const int CAUSE_LENGTH = 120;

    /**
     * Statuses of the bar and of the legend, most severe first.
     */
    public const array STATUS_ORDER = [
        ReportStatus::Failed,
        ReportStatus::Warning,
        ReportStatus::Unknown,
        ReportStatus::Success,
    ];

    /**
     * @var array<string, int>
     */
    private array $totals;

    private int $staleCount = 0;

    private ?DateTimeImmutable $latestRunAt = null;

    /**
     * @param list<\App\Report\HostReport> $reports Reports of the fleet.
     * @param \DateTimeImmutable $now Current time.
     */
    public function __construct(private readonly array $reports, private readonly DateTimeImmutable $now)
    {
        $this->totals = array_fill_keys(array_column(ReportStatus::cases(), 'value'), 0);
        foreach ($reports as $report) {
            $this->totals[$report->status()->value]++;
            $this->staleCount += (int)$report->isStale($now);
            $ranAt = $report->ranAt();
            if ($this->latestRunAt === null || $ranAt > $this->latestRunAt) {
                $this->latestRunAt = $ranAt;
            }
        }
    }

    /**
     * Returns the number of workstations by status value.
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        return $this->totals;
    }

    /**
     * Returns the number of workstations that reported.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->reports);
    }

    /**
     * Returns the number of workstations that did not run in the last 24 hours.
     *
     * @return int
     */
    public function staleCount(): int
    {
        return $this->staleCount;
    }

    /**
     * Returns when the most recent run started.
     *
     * @return \DateTimeImmutable|null Null without reports.
     */
    public function latestRunAt(): ?DateTimeImmutable
    {
        return $this->latestRunAt;
    }

    /**
     * Returns the workstations to treat first: failed, warning, unknown, then stale successes.
     *
     * Each category lists the most recent run first.
     *
     * @param int $limit Maximum number of rows.
     * @return list<\App\Report\HostReport>
     */
    public function needsAttention(int $limit = self::ATTENTION_LIMIT): array
    {
        $needing = array_values(array_filter(
            $this->reports,
            fn(HostReport $report): bool => $this->rank($report) !== null,
        ));
        usort($needing, function (HostReport $left, HostReport $right): int {
            return [$this->rank($left), $right->ranAt()->getTimestamp()]
                <=> [$this->rank($right), $left->ranAt()->getTimestamp()];
        });

        return array_slice($needing, 0, $limit);
    }

    /**
     * Explains in one line why a workstation needs attention.
     *
     * @param \App\Report\HostReport $report Report.
     * @return string Whole text, not shortened.
     */
    public function cause(HostReport $report): string
    {
        $message = match ($report->status()) {
            ReportStatus::Failed => $report->log?->errors()[0] ?? '',
            ReportStatus::Warning => $report->log?->warnings()[0] ?? '',
            ReportStatus::Unknown => 'No readable log',
            ReportStatus::Success => sprintf(
                'No report for %d hours',
                intdiv($this->now->getTimestamp() - $report->ranAt()->getTimestamp(), 3600),
            ),
        };

        return trim((string)preg_replace('/\s+/', ' ', $message));
    }

    /**
     * Shortens a cause for display.
     *
     * @param string $cause Cause.
     * @return string
     */
    public static function shorten(string $cause): string
    {
        return mb_strimwidth($cause, 0, self::CAUSE_LENGTH, '…');
    }

    /**
     * Computes the segments of the status bar, in percent of its width.
     *
     * @return list<array{status: \App\Report\ReportStatus, count: int, offset: float, width: float}>
     */
    public function barSegments(): array
    {
        $segments = [];
        $counted = 0;
        foreach (self::STATUS_ORDER as $status) {
            $count = $this->totals[$status->value];
            if ($count === 0) {
                continue;
            }
            $segments[] = [
                'status' => $status,
                'count' => $count,
                'offset' => round($counted / $this->count() * 100, 2),
                'width' => round($count / $this->count() * 100, 2),
            ];
            $counted += $count;
        }

        return $segments;
    }

    /**
     * Returns the sort rank of a report, null when it needs no attention.
     *
     * @param \App\Report\HostReport $report Report.
     * @return int|null
     */
    private function rank(HostReport $report): ?int
    {
        return match ($report->status()) {
            ReportStatus::Failed => 0,
            ReportStatus::Warning => 1,
            ReportStatus::Unknown => 2,
            ReportStatus::Success => $report->isStale($this->now) ? 3 : null,
        };
    }
}
