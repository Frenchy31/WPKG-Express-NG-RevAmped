<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\ReportDirectory;
use App\Report\ReportStatus;
use App\Test\Support\ReportFixturesTrait;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;

/**
 * Reading of the reports directory.
 */
class ReportDirectoryTest extends TestCase
{
    use ReportFixturesTrait;

    private ReportDirectory $directory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = new ReportDirectory($this->copyReportFixtures());
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
     * A workstation appears once, whether it has a log, an XML file or both.
     *
     * @return void
     */
    public function testAllPairsFiles(): void
    {
        $hostnames = array_map(fn($report): string => $report->hostname, $this->directory->all());
        sort($hostnames);

        $this->assertSame(['BROKEN', 'DEV-7', 'ROOM1-PC01', 'ROOM1-PC02', 'ROOM2-PC05'], $hostnames);
    }

    /**
     * Both files of a workstation are read.
     *
     * @return void
     */
    public function testFindReadsLogAndXml(): void
    {
        $report = $this->directory->find('ROOM1-PC01');

        $this->assertNotNull($report);
        $this->assertSame(ReportStatus::Success, $report->status());
        $this->assertTrue($report->hasXml);
        $this->assertSame(2, $report->installedPackages);
        $this->assertSame('microsoft windows 11 éducation, , , 10.0.26200', $report->properties['os']);
        $this->assertSame(['192.168.1.21'], $report->ipAddresses());
        $this->assertNull($report->xmlError);
    }

    /**
     * Without a log, the status is unknown and the run time comes from the file.
     *
     * @return void
     */
    public function testFindWithXmlOnly(): void
    {
        $modifiedAt = (int)strtotime('2026-11-01 10:00:00');
        touch($this->reportsPath . DS . 'DEV-7.xml', $modifiedAt);

        $report = $this->directory->find('DEV-7');

        $this->assertNotNull($report);
        $this->assertNull($report->log);
        $this->assertSame(ReportStatus::Unknown, $report->status());
        $this->assertSame(0, $report->installedPackages);
        $this->assertSame($modifiedAt, $report->ranAt()->getTimestamp());
    }

    /**
     * A malformed XML file is reported, not thrown.
     *
     * @return void
     */
    public function testFindWithMalformedXml(): void
    {
        $report = $this->directory->find('BROKEN');

        $this->assertNotNull($report);
        $this->assertNull($report->installedPackages);
        $this->assertNotNull($report->xmlError);
    }

    /**
     * Names that could leave the directory, and unknown names, give nothing.
     *
     * @return void
     */
    public function testFindRejectsInvalidNames(): void
    {
        foreach (['../etc/passwd', '..', 'ROOM1-PC01/../DEV-7', '', 'UNKNOWN', 'notes'] as $hostname) {
            $this->assertNull($this->directory->find($hostname), $hostname);
        }
    }

    /**
     * A report becomes stale 24 hours after its run.
     *
     * @return void
     */
    public function testStale(): void
    {
        $report = $this->directory->find('ROOM1-PC01');

        $this->assertNotNull($report);
        $this->assertFalse($report->isStale(new DateTimeImmutable('2026-11-09 02:11:11')));
        $this->assertTrue($report->isStale(new DateTimeImmutable('2026-11-09 02:11:12')));
    }

    /**
     * Without a configured path, the directory is empty.
     *
     * @return void
     */
    public function testNotConfigured(): void
    {
        $directory = new ReportDirectory('');

        $this->assertFalse($directory->isConfigured());
        $this->assertFalse($directory->isReadable());
        $this->assertSame([], $directory->all());
        $this->assertNull($directory->find('ROOM1-PC01'));
    }
}
