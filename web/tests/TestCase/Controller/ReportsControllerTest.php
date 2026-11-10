<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Report\ReportDirectory;
use App\Report\ReportIndexer;
use App\Test\Support\AdminIntegrationTestCase;
use App\Test\Support\ReportFixturesTrait;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Reports page.
 */
class ReportsControllerTest extends AdminIntegrationTestCase
{
    use LocatorAwareTrait;
    use ReportFixturesTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Wpkg.reportsPath', $this->copyReportFixtures());
        $this->indexReportFixtures();
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
     * Workstations are grouped by host entry, colored and linked to their log.
     *
     * @return void
     */
    public function testIndex(): void
    {
        $this->get('/reports');

        $this->assertResponseOk();
        $this->assertResponseContains('room1-pc.+');
        $this->assertResponseContains('report-group status-failed');
        $this->assertResponseContains('report status-success');
        $this->assertResponseContains('href="/reports/ROOM1-PC02"');
        $this->assertResponseContains('Failed checking after installation.');
        $this->assertResponseContains('Failed: 1');
    }

    /**
     * The log page shows every entry with its level.
     *
     * @return void
     */
    public function testView(): void
    {
        $this->get('/reports/ROOM1-PC02');

        $this->assertResponseOk();
        $this->assertResponseContains('class="level-error"');
        $this->assertResponseContains('Command output: a | b');
    }

    /**
     * Unknown and invalid host names are not found.
     *
     * @return void
     */
    public function testViewNotFound(): void
    {
        foreach (['/reports/UNKNOWN', '/reports/view/..', '/reports/..%2Fconfig'] as $url) {
            $this->get($url);
            $this->assertResponseCode(404, $url);
        }
    }

    /**
     * Without a directory, the page explains how to configure it.
     *
     * @return void
     */
    public function testNotConfigured(): void
    {
        Configure::write('Wpkg.reportsPath', '');

        $this->get('/reports');

        $this->assertResponseOk();
        $this->assertResponseContains('WPKG_REPORTS_PATH');
    }

    /**
     * The list comes from the index: it renders the same when the directory cannot be opened.
     *
     * @return void
     */
    public function testIndexWithoutDirectory(): void
    {
        Configure::write('Wpkg.reportsPath', $this->reportsPath . DS . 'missing');

        $this->get('/reports');

        $this->assertResponseOk();
        $this->assertResponseContains('room1-pc.+');
        $this->assertResponseContains('Failed: 1');
        $this->assertResponseContains('Failed checking after installation.');
        $this->assertResponseNotContains('Reports directory cannot be read');
    }

    /**
     * Changes of the files wait for the next pass on the list, but the log page reads the file.
     *
     * @return void
     */
    public function testViewReadsTheFile(): void
    {
        file_put_contents(
            $this->reportsPath . DS . 'ROOM1-PC02.log',
            "2026-11-08 05:00:00, ERROR   : Written after the indexing.\n",
        );

        $this->get('/reports/ROOM1-PC02');
        $this->assertResponseContains('Written after the indexing.');

        $this->get('/reports');
        $this->assertResponseNotContains('Written after the indexing.');
        $this->indexReportFixtures();
        $this->get('/reports');
        $this->assertResponseContains('Written after the indexing.');
    }

    /**
     * The page shows the end of the last pass and no warning when it is recent.
     *
     * @return void
     */
    public function testLastPassIsShown(): void
    {
        $this->get('/reports');

        $this->assertResponseContains('Last indexing: ');
        $this->assertResponseNotContains('has not run since');
        $this->assertResponseNotContains('has never run');
    }

    /**
     * A pass older than three intervals warns that the indexer stopped.
     *
     * @return void
     */
    public function testOverdueIndexerWarns(): void
    {
        Configure::write('Wpkg.reportsIndexInterval', 60);
        $this->fetchTable('ReportIndexState')->updateAll(['finished_at' => time() - 181], ['id' => 1]);

        $this->get('/reports');
        $this->assertResponseContains('The reports indexer has not run since');

        $this->fetchTable('ReportIndexState')->updateAll(['finished_at' => time() - 170], ['id' => 1]);
        $this->get('/reports');
        $this->assertResponseNotContains('The reports indexer has not run since');
    }

    /**
     * Without any pass, the page tells to start the indexer.
     *
     * @return void
     */
    public function testNeverIndexedWarns(): void
    {
        $this->fetchTable('ReportIndexState')->deleteAll([]);
        $this->fetchTable('HostReports')->deleteAll([]);

        $this->get('/reports');

        $this->assertResponseContains('The reports indexer has never run');
        $this->assertResponseContains('No reports found');
    }

    /**
     * A failed pass shows its error.
     *
     * @return void
     */
    public function testFailedPassShowsItsError(): void
    {
        (new ReportIndexer(new ReportDirectory($this->reportsPath . DS . 'missing')))->run();

        $this->get('/reports');

        $this->assertResponseContains('The last indexing failed: Reports directory cannot be read');
    }
}
