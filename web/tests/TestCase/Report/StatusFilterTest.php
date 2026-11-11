<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\HostReport;
use App\Report\ReportGroup;
use App\Report\RunLog;
use App\Report\StatusFilter;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;

/**
 * Choice of the statuses shown on the room map.
 */
class StatusFilterTest extends TestCase
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
     * Builds a report whose log has the given entry, or no log.
     *
     * @param string $hostname Host name.
     * @param string $runTime Start time of the run.
     * @param string|null $level Level of the entry after the start entry, null for a clean run.
     * @param bool $withLog Whether the workstation has a log at all.
     * @return \App\Report\HostReport
     */
    private function report(string $hostname, string $runTime, ?string $level = null, bool $withLog = true): HostReport
    {
        $content = $runTime . ", DEBUG   : WPKG 1.3.1 starting...\n";
        if ($level !== null) {
            $content .= $runTime . ', ' . str_pad($level, 7) . ": Message\n";
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
     * Builds two groups: fresh success and stale failure, then stale success and stale warning.
     *
     * @return list<\App\Report\ReportGroup>
     */
    private function fleetGroups(): array
    {
        return [
            new ReportGroup(null, [
                $this->report('A', '2026-11-09 03:00:00'),
                $this->report('B', '2026-11-01 03:00:00', 'ERROR'),
            ], 'first'),
            new ReportGroup(null, [
                $this->report('C', '2026-11-01 03:00:00'),
                $this->report('D', '2026-11-01 03:00:00', 'WARNING'),
                $this->report('E', '2026-11-09 03:00:00', withLog: false),
            ], 'second'),
        ];
    }

    /**
     * Lists the host names shown by group position.
     *
     * @param \App\Report\StatusFilter $filter Filter.
     * @return array<int, list<string>>
     */
    private function shown(StatusFilter $filter): array
    {
        return array_map(
            fn(array $reports): array => array_map(fn(HostReport $report): string => $report->hostname, $reports),
            $filter->visibleReports($this->fleetGroups(), $this->now),
        );
    }

    /**
     * Without the form field, failed and warning are checked.
     *
     * @return void
     */
    public function testDefault(): void
    {
        $filter = StatusFilter::fromQuery(['status' => ['success']]);

        $this->assertTrue($filter->isChecked('failed'));
        $this->assertTrue($filter->isChecked('warning'));
        $this->assertFalse($filter->isChecked('success'));
        $this->assertFalse($filter->isChecked('stale'));
        $this->assertSame([0 => ['B'], 1 => ['D']], $this->shown($filter));
    }

    /**
     * A submitted form with nothing checked shows nothing, and unknown values are dropped.
     *
     * @return void
     */
    public function testSubmittedFormAndUnknownValues(): void
    {
        $this->assertSame([], $this->shown(StatusFilter::fromQuery(['filter' => '1'])));
        $this->assertSame(
            [],
            $this->shown(StatusFilter::fromQuery(['filter' => '1', 'status' => ['bogus', ['failed'], 'Failed']])),
        );
        $this->assertSame(
            [0 => ['B']],
            $this->shown(StatusFilter::fromQuery(['filter' => '1', 'status' => ['bogus', 'failed']])),
        );
    }

    /**
     * Stale adds to the statuses, and a group left without workstation is dropped.
     *
     * @return void
     */
    public function testStaleIsAUnion(): void
    {
        $this->assertSame(
            [0 => ['B'], 1 => ['C', 'D']],
            $this->shown(StatusFilter::fromQuery(['filter' => '1', 'status' => ['stale']])),
        );
        $this->assertSame(
            [0 => ['B'], 1 => ['C', 'D', 'E']],
            $this->shown(StatusFilter::fromQuery(['filter' => '1', 'status' => ['stale', 'unknown']])),
        );
        $this->assertSame(
            [1 => ['E']],
            $this->shown(StatusFilter::fromQuery(['filter' => '1', 'status' => ['unknown']])),
        );
    }
}
