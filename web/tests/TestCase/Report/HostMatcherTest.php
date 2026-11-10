<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\HostMatcher;
use App\Test\Support\ReferenceDataTrait;
use App\Test\Support\ReportFixturesTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

/**
 * Matching of workstations against host entries, as wpkg.js does.
 *
 * Reference hosts, in feed order: room2-pc.+ (2), room1-pc.+ (1), ^dev-[0-9]+$ (4), .+ (5).
 * old-pc.+ (3) is disabled.
 */
class HostMatcherTest extends TestCase
{
    use LocatorAwareTrait;
    use ReferenceDataTrait;
    use ReportFixturesTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->removeReportFixtures();
        parent::tearDown();
    }

    /**
     * Expressions are anchored and ignore case, disabled entries never match.
     *
     * @return void
     */
    public function testMatchesExpressions(): void
    {
        $matcher = new HostMatcher();

        $this->assertSame(1, $matcher->match('ROOM1-PC01', [])?->id);
        $this->assertSame(2, $matcher->match('room2-pc05', [])?->id);
        $this->assertSame(4, $matcher->match('DEV-7', [])?->id);
        $this->assertSame(5, $matcher->match('dev-7x', [])?->id);
        $this->assertSame(5, $matcher->match('OLD-PC1', [])?->id);
    }

    /**
     * The first entry in feed order wins.
     *
     * @return void
     */
    public function testFirstMatchInFeedOrderWins(): void
    {
        $this->fetchTable('Hosts')->updateAll(['name' => 'room.+'], ['id' => 1]);
        $matcher = new HostMatcher();

        $this->assertSame(2, $matcher->match('ROOM2-PC05', [])?->id);
        $this->assertSame(1, $matcher->match('ROOM1-PC01', [])?->id);
    }

    /**
     * Names are compared as is before being read as expressions, and invalid expressions are skipped.
     *
     * @return void
     */
    public function testExactNameAndInvalidExpression(): void
    {
        $hosts = $this->fetchTable('Hosts');
        $hosts->updateAll(['name' => 'LAB(1)'], ['id' => 4]);
        $hosts->updateAll(['name' => 'room1-pc(('], ['id' => 1]);
        $matcher = new HostMatcher();

        $this->assertSame(4, $matcher->match('lab(1)', [])?->id);
        $this->assertSame(5, $matcher->match('ROOM1-PC01', [])?->id);
    }

    /**
     * An entry named after an IP range matches the addresses of the workstation.
     *
     * @return void
     */
    public function testMatchesIpRange(): void
    {
        $this->fetchTable('Hosts')->updateAll(['name' => '192.168.1-2.10-30'], ['id' => 2]);
        $matcher = new HostMatcher();

        $this->assertSame(2, $matcher->match('LAB-PC', ['10.0.0.1', '192.168.2.21'])?->id);
        $this->assertSame(5, $matcher->match('LAB-PC', ['192.168.2.31'])?->id);
        $this->assertSame(5, $matcher->match('LAB-PC', ['192.168.3.21'])?->id);
    }

    /**
     * Groups follow the feed order and end with the workstations no entry matches.
     *
     * @return void
     */
    public function testGroup(): void
    {
        $this->fetchTable('Hosts')->updateAll(['enabled' => false], ['id' => 5]);
        $this->copyReportFixtures();
        $this->indexReportFixtures();
        $reports = $this->fetchTable('HostReports')->allReports();

        $groups = (new HostMatcher())->group($reports);

        $summary = [];
        foreach ($groups as $group) {
            $summary[$group->label()] = array_map(fn($report): string => $report->hostname, $group->reports);
        }
        $this->assertSame([
            'room2-pc.+' => ['ROOM2-PC05'],
            'room1-pc.+' => ['ROOM1-PC01', 'ROOM1-PC02'],
            '^dev-[0-9]+$' => ['DEV-7'],
            'Unmatched' => ['BROKEN'],
        ], $summary);
        $this->assertSame('failed', $groups[1]->status()->value);
        $this->assertNull($groups[3]->profile());
    }
}
