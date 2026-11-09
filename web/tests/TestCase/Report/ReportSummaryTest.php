<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\HostReport;
use App\Report\ReportStatus;
use App\Report\ReportSummary;
use App\Report\RunLog;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;

/**
 * Totals and "Needs attention" list of the fleet.
 */
class ReportSummaryTest extends TestCase
{
    private DateTimeImmutable $now;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-11-09 12:00:00');
    }

    /**
     * Builds a report whose log has the given entries, or no log.
     *
     * @param string $hostname Host name.
     * @param string $runTime Start time of the run.
     * @param string|null $level Level of the entry after the start entry, null for a clean run.
     * @param string $message Message of that entry.
     * @param bool $withLog Whether the workstation has a log at all.
     * @return \App\Report\HostReport
     */
    private function report(
        string $hostname,
        string $runTime,
        ?string $level = null,
        string $message = '',
        bool $withLog = true,
    ): HostReport {
        $content = $runTime . ", DEBUG   : WPKG 1.3.1 starting...\n";
        if ($level !== null) {
            $content .= $runTime . ', ' . str_pad($level, 7) . ': ' . $message . "\n";
        }

        return new HostReport(
            $hostname,
            $withLog ? RunLog::parse($content) : null,
            false,
            [],
            null,
            null,
            new DateTimeImmutable($runTime),
        );
    }

    /**
     * Totals count each status once, stale reports whatever their status, and the latest run is kept.
     *
     * @return void
     */
    public function testTotalsStaleAndLatestRun(): void
    {
        $summary = new ReportSummary([
            $this->report('A', '2026-11-09 03:00:00'),
            $this->report('B', '2026-11-09 04:00:00', 'ERROR', 'Boom'),
            $this->report('C', '2026-11-07 04:00:00', 'WARNING', 'Careful'),
            $this->report('D', '2026-11-08 20:00:00', withLog: false),
        ], $this->now);

        $this->assertSame(
            ['success' => 1, 'unknown' => 1, 'warning' => 1, 'failed' => 1],
            $summary->totals(),
        );
        $this->assertSame(4, $summary->count());
        $this->assertSame(1, $summary->staleCount());
        $this->assertSame('2026-11-09 04:00:00', $summary->latestRunAt()?->format('Y-m-d H:i:s'));
    }

    /**
     * Without reports there is no latest run.
     *
     * @return void
     */
    public function testEmpty(): void
    {
        $summary = new ReportSummary([], $this->now);

        $this->assertNull($summary->latestRunAt());
        $this->assertSame([], $summary->needsAttention());
        $this->assertSame([], $summary->barSegments());
    }

    /**
     * Failed come first, then warning, unknown and stale successes, newest first in each category.
     *
     * @return void
     */
    public function testNeedsAttentionOrder(): void
    {
        $summary = new ReportSummary([
            $this->report('FRESH-OK', '2026-11-09 11:00:00'),
            $this->report('STALE-OK-OLD', '2026-11-05 11:00:00'),
            $this->report('STALE-OK-NEW', '2026-11-08 11:00:00'),
            $this->report('UNKNOWN', '2026-11-09 10:00:00', withLog: false),
            $this->report('WARN-OLD', '2026-11-09 01:00:00', 'WARNING', 'w'),
            $this->report('WARN-NEW', '2026-11-09 09:00:00', 'WARNING', 'w'),
            $this->report('FAIL-OLD', '2026-11-09 02:00:00', 'ERROR', 'e'),
            $this->report('FAIL-NEW', '2026-11-09 03:00:00', 'ERROR', 'e'),
        ], $this->now);

        $hostnames = array_map(fn(HostReport $report): string => $report->hostname, $summary->needsAttention());

        $this->assertSame([
            'FAIL-NEW',
            'FAIL-OLD',
            'WARN-NEW',
            'WARN-OLD',
            'UNKNOWN',
            'STALE-OK-NEW',
            'STALE-OK-OLD',
        ], $hostnames);
    }

    /**
     * The list stops at ten rows, keeping the most severe.
     *
     * @return void
     */
    public function testNeedsAttentionLimit(): void
    {
        $reports = [$this->report('WARN', '2026-11-09 11:00:00', 'WARNING', 'w')];
        foreach (range(1, 12) as $number) {
            $reports[] = $this->report('FAIL-' . $number, '2026-11-09 03:00:' . sprintf('%02d', $number), 'ERROR', 'e');
        }

        $needing = (new ReportSummary($reports, $this->now))->needsAttention();

        $this->assertCount(10, $needing);
        $this->assertSame('FAIL-12', $needing[0]->hostname);
        $this->assertSame('FAIL-3', $needing[9]->hostname);
    }

    /**
     * The cause is the first error, the first warning, a fixed text or the age of the report.
     *
     * @return void
     */
    public function testCause(): void
    {
        $summary = new ReportSummary([], $this->now);

        $failed = $this->report('A', '2026-11-09 03:00:00', 'ERROR', 'Install of 7-Zip failed.|Check failed');
        $warning = $this->report('B', '2026-11-09 03:00:00', 'WARNING', 'Reboot postponed');
        $unreadable = $this->report('C', '2026-11-09 03:00:00', withLog: false);
        $stale = $this->report('D', '2026-11-07 10:00:00');

        $this->assertSame('Install of 7-Zip failed. Check failed', $summary->cause($failed));
        $this->assertSame('Reboot postponed', $summary->cause($warning));
        $this->assertSame('No readable log', $summary->cause($unreadable));
        $this->assertSame('No report for 50 hours', $summary->cause($stale));
    }

    /**
     * Long causes are cut at 120 characters.
     *
     * @return void
     */
    public function testShorten(): void
    {
        $this->assertSame('short', ReportSummary::shorten('short'));
        $this->assertSame(120, mb_strlen(ReportSummary::shorten(str_repeat('é', 300))));
        $this->assertStringEndsWith('…', ReportSummary::shorten(str_repeat('x', 121)));
        $this->assertSame(str_repeat('x', 120), ReportSummary::shorten(str_repeat('x', 120)));
    }

    /**
     * The bar has one segment per present status, severe first, filling the whole width.
     *
     * @return void
     */
    public function testBarSegments(): void
    {
        $summary = new ReportSummary([
            $this->report('A', '2026-11-09 03:00:00'),
            $this->report('B', '2026-11-09 03:00:00'),
            $this->report('C', '2026-11-09 03:00:00', 'ERROR', 'e'),
            $this->report('D', '2026-11-09 03:00:00', withLog: false),
        ], $this->now);

        $segments = $summary->barSegments();

        $this->assertSame(
            [ReportStatus::Failed, ReportStatus::Unknown, ReportStatus::Success],
            array_column($segments, 'status'),
        );
        $this->assertSame([0.0, 25.0, 50.0], array_column($segments, 'offset'));
        $this->assertSame([25.0, 25.0, 50.0], array_column($segments, 'width'));
    }
}
