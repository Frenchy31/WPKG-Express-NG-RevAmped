<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use App\Test\Support\ReportFixturesTrait;
use Cake\Core\Configure;

/**
 * Reports page.
 */
class ReportsControllerTest extends AdminIntegrationTestCase
{
    use ReportFixturesTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Wpkg.reportsPath', $this->copyReportFixtures());
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
}
