<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\ReportDirectory;
use App\Report\ReportIndexer;
use App\Test\Support\ReportFixturesTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

/**
 * Indexing of the reports directory into host_reports.
 */
class ReportIndexerTest extends TestCase
{
    use LocatorAwareTrait;
    use ReportFixturesTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->copyReportFixtures();
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
     * Reads a row of the index.
     *
     * @param string $hostname Host name.
     * @return array<string, mixed>
     */
    private function row(string $hostname): array
    {
        $row = $this->fetchTable('HostReports')->find()->where(['hostname' => $hostname])->disableHydration()->first();
        $this->assertIsArray($row, $hostname);

        return $row;
    }

    /**
     * Counts the rows of the index.
     *
     * @return int
     */
    private function rowCount(): int
    {
        return $this->fetchTable('HostReports')->find()->count();
    }

    /**
     * Gives a file a new modification date.
     *
     * @param string $hostname Host name.
     * @param string $extension Extension, log or xml.
     * @param int $secondsLater Seconds to add to the current date.
     * @return void
     */
    private function touchFile(string $hostname, string $extension, int $secondsLater): void
    {
        $path = $this->reportsPath . DS . $hostname . '.' . $extension;
        touch($path, (int)filemtime($path) + $secondsLater);
        clearstatcache();
    }

    /**
     * A new workstation gets one row holding the summary of its two files.
     *
     * @return void
     */
    public function testNewHostsAreIndexed(): void
    {
        $state = $this->indexReportFixtures();

        $this->assertSame(6, $state->filesSeen);
        $this->assertSame(6, $state->filesRead);
        $this->assertSame(0, $state->hostsRemoved);
        $this->assertNull($state->error);
        $this->assertSame(5, $this->rowCount());
        $row = $this->row('ROOM1-PC01');
        $this->assertSame('success', $row['status']);
        $this->assertSame(2, $row['installed_packages']);
        $this->assertTrue($row['has_xml']);
        $this->assertSame('192.168.1.21', json_decode($row['properties'], true)['ipaddresses']);
        $this->assertSame(['base', 'room1'], json_decode($row['profiles'], true));
        $this->assertCount(2, json_decode($row['succeeded'], true));
        $this->assertSame('failed', $this->row('ROOM1-PC02')['status']);
        $this->assertNotSame([], json_decode($this->row('ROOM1-PC02')['errors'], true));
        $this->assertSame('unknown', $this->row('DEV-7')['status']);
        $this->assertNull($this->row('DEV-7')['log_mtime']);
    }

    /**
     * The row rebuilds the report the pages show.
     *
     * @return void
     */
    public function testRowBuildsHostReport(): void
    {
        $this->indexReportFixtures();

        $reports = [];
        foreach ($this->fetchTable('HostReports')->allReports() as $report) {
            $reports[$report->hostname] = $report;
        }

        $fromFiles = (new ReportDirectory($this->reportsPath))->find('ROOM1-PC02');
        $this->assertNotNull($fromFiles);
        $fromIndex = $reports['ROOM1-PC02'];
        $this->assertSame($fromFiles->status(), $fromIndex->status());
        $this->assertSame($fromFiles->ranAt()->getTimestamp(), $fromIndex->ranAt()->getTimestamp());
        $this->assertSame($fromFiles->log?->errors(), $fromIndex->log?->errors());
        $this->assertSame($fromFiles->log?->warnings(), $fromIndex->log?->warnings());
        $this->assertSame($fromFiles->log?->succeeded(), $fromIndex->log?->succeeded());
        $this->assertSame($fromFiles->log?->profiles(), $fromIndex->log?->profiles());
        $this->assertSame($fromFiles->log?->wpkgVersion(), $fromIndex->log?->wpkgVersion());
        $this->assertSame($fromFiles->ipAddresses(), $fromIndex->ipAddresses());
        $this->assertNull($reports['DEV-7']->log);
    }

    /**
     * Files whose date and size did not change are not read again.
     *
     * @return void
     */
    public function testUnchangedFilesAreNotRead(): void
    {
        $this->indexReportFixtures();
        $this->fetchTable('HostReports')->updateAll(['wpkg_version' => 'sentinel'], ['hostname' => 'ROOM1-PC01']);

        $state = $this->indexReportFixtures();

        $this->assertSame(6, $state->filesSeen);
        $this->assertSame(0, $state->filesRead);
        $this->assertSame('sentinel', $this->row('ROOM1-PC01')['wpkg_version']);
    }

    /**
     * A log still written by the workstation is read again, the other file is kept.
     *
     * @return void
     */
    public function testChangedLogIsReadAgain(): void
    {
        $this->indexReportFixtures();
        $path = $this->reportsPath . DS . 'ROOM1-PC01.log';
        file_put_contents($path, "2026-11-08 03:00:00, ERROR   : Something broke.\n", FILE_APPEND);
        $this->touchFile('ROOM1-PC01', 'log', 10);

        $state = $this->indexReportFixtures();

        $this->assertSame(1, $state->filesRead);
        $row = $this->row('ROOM1-PC01');
        $this->assertSame('failed', $row['status']);
        $this->assertContains('Something broke.', json_decode($row['errors'], true));
        $this->assertSame(2, $row['installed_packages']);
    }

    /**
     * A file rewritten with the same date but another size is read again.
     *
     * @return void
     */
    public function testChangedSizeAloneIsDetected(): void
    {
        $this->indexReportFixtures();
        $path = $this->reportsPath . DS . 'ROOM1-PC01.log';
        $modifiedAt = (int)filemtime($path);
        file_put_contents($path, "2026-11-08 03:00:00, WARNING : Slow disk.\n", FILE_APPEND);
        touch($path, $modifiedAt);
        clearstatcache();

        $state = $this->indexReportFixtures();

        $this->assertSame(1, $state->filesRead);
        $this->assertSame('warning', $this->row('ROOM1-PC01')['status']);
    }

    /**
     * A changed wpkg.xml updates the XML columns only.
     *
     * @return void
     */
    public function testChangedXmlIsReadAgain(): void
    {
        $this->indexReportFixtures();
        $path = $this->reportsPath . DS . 'ROOM1-PC01.xml';
        file_put_contents($path, '<wpkg hostname="room1-pc01"><package id="a"/></wpkg>');
        $this->touchFile('ROOM1-PC01', 'xml', 10);

        $state = $this->indexReportFixtures();

        $this->assertSame(1, $state->filesRead);
        $row = $this->row('ROOM1-PC01');
        $this->assertSame(1, $row['installed_packages']);
        $this->assertSame('success', $row['status']);
    }

    /**
     * The package ids of wpkg.xml are stored, empty without a readable file, and give hasAgent().
     *
     * @return void
     */
    public function testPackageIdsAreStored(): void
    {
        $this->writeAgentReport('DEV-7');

        $this->indexReportFixtures();

        $this->assertSame(['7zip', 'firefox'], json_decode($this->row('ROOM1-PC01')['package_ids'], true));
        $this->assertSame(['wpkg-agent'], json_decode($this->row('DEV-7')['package_ids'], true));
        $this->assertSame('[]', $this->row('BROKEN')['package_ids']);
        $this->assertSame('[]', $this->row('ROOM1-PC02')['package_ids']);
        $reports = [];
        foreach ($this->fetchTable('HostReports')->allReports() as $report) {
            $reports[$report->hostname] = $report;
        }
        $this->assertTrue($reports['DEV-7']->hasAgent());
        $this->assertFalse($reports['ROOM1-PC01']->hasAgent());
    }

    /**
     * Clearing the recorded xml dates, as the package_ids migration does, reads every wpkg.xml again.
     *
     * @return void
     */
    public function testClearedXmlDatesFillPackageIds(): void
    {
        $this->indexReportFixtures();
        $this->fetchTable('HostReports')->updateAll(
            ['package_ids' => '[]', 'xml_mtime' => null, 'xml_size' => null],
            [],
        );

        $state = $this->indexReportFixtures();

        $this->assertSame(3, $state->filesRead);
        $this->assertSame(['7zip', 'firefox'], json_decode($this->row('ROOM1-PC01')['package_ids'], true));
        $this->assertSame(0, $this->indexReportFixtures()->filesRead);
    }

    /**
     * A workstation whose two files are gone leaves the index, one whose second file remains stays.
     *
     * @return void
     */
    public function testVanishedHostsAreRemoved(): void
    {
        $this->indexReportFixtures();
        unlink($this->reportsPath . DS . 'ROOM1-PC01.log');
        unlink($this->reportsPath . DS . 'ROOM1-PC02.log');
        unlink($this->reportsPath . DS . 'DEV-7.xml');

        $state = $this->indexReportFixtures();

        $this->assertSame(2, $state->hostsRemoved);
        $this->assertSame(3, $this->rowCount());
        $row = $this->row('ROOM1-PC01');
        $this->assertNull($row['log_mtime']);
        $this->assertSame('unknown', $row['status']);
        $this->assertSame(2, $row['installed_packages']);
    }

    /**
     * An empty log and an invalid XML file are recorded on the workstation and stop nothing.
     *
     * @return void
     */
    public function testBadFilesAreRecorded(): void
    {
        file_put_contents($this->reportsPath . DS . 'ROOM1-PC02.log', '');

        $state = $this->indexReportFixtures();

        $this->assertNull($state->error);
        $this->assertSame(5, $this->rowCount());
        $this->assertSame('The file is empty.', $this->row('ROOM1-PC02')['log_error']);
        $this->assertSame('unknown', $this->row('ROOM1-PC02')['status']);
        $broken = $this->row('BROKEN');
        $this->assertNotNull($broken['xml_error']);
        $this->assertNull($broken['installed_packages']);
        $this->assertSame('success', $this->row('ROOM1-PC01')['status']);
    }

    /**
     * Names that are not workstation names are ignored.
     *
     * @return void
     */
    public function testForeignFilesAreIgnored(): void
    {
        file_put_contents($this->reportsPath . DS . 'bad name.log', 'x');
        file_put_contents($this->reportsPath . DS . 'ROOM9-PC01.txt', 'x');

        $state = $this->indexReportFixtures();

        $this->assertSame(6, $state->filesSeen);
        $this->assertSame(5, $this->rowCount());
    }

    /**
     * Host names differing by case are two workstations.
     *
     * @return void
     */
    public function testHostnamesAreCaseSensitive(): void
    {
        copy($this->reportsPath . DS . 'ROOM1-PC02.log', $this->reportsPath . DS . 'room1-pc02.log');

        $this->indexReportFixtures();

        $this->assertSame(6, $this->rowCount());
    }

    /**
     * Each pass records its end, even when nothing was read.
     *
     * @return void
     */
    public function testPassIsRecorded(): void
    {
        $this->indexReportFixtures();
        $first = $this->fetchTable('ReportIndexState')->latest();
        $this->assertNotNull($first);
        $this->assertSame(6, $first->filesRead);

        $this->indexReportFixtures();

        $second = $this->fetchTable('ReportIndexState')->latest();
        $this->assertNotNull($second);
        $this->assertSame(0, $second->filesRead);
        $this->assertSame(1, $this->fetchTable('ReportIndexState')->find()->count());
        $this->assertGreaterThanOrEqual($first->finishedAt->getTimestamp(), $second->finishedAt->getTimestamp());
    }

    /**
     * A directory that cannot be listed records the error and keeps the index.
     *
     * @return void
     */
    public function testMissingDirectoryKeepsRows(): void
    {
        $this->indexReportFixtures();

        $state = (new ReportIndexer(new ReportDirectory($this->reportsPath . DS . 'missing')))->run();

        $this->assertSame('Reports directory cannot be read', $state->error);
        $this->assertSame(0, $state->filesSeen);
        $this->assertSame(5, $this->rowCount());
        $this->assertSame('Reports directory cannot be read', $this->fetchTable('ReportIndexState')->latest()?->error);
    }
}
