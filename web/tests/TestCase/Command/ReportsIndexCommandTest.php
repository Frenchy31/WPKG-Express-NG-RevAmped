<?php

declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\Support\ReportFixturesTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

/**
 * The reports index command.
 */
class ReportsIndexCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use LocatorAwareTrait;
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
        ConnectionManager::drop('lock_holder');
        parent::tearDown();
    }

    /**
     * A pass prints what it saw and read, then a second one reads nothing.
     *
     * @return void
     */
    public function testPassesPrintTheirCounters(): void
    {
        $this->exec('reports index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Files seen: 6, files read: 6, hosts removed: 0, duration: ');
        $this->assertSame(5, $this->fetchTable('HostReports')->find()->count());

        $this->exec('reports index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Files seen: 6, files read: 0, hosts removed: 0, duration: ');
    }

    /**
     * Without a configured directory, the command does nothing and succeeds.
     *
     * @return void
     */
    public function testNotConfigured(): void
    {
        Configure::write('Wpkg.reportsPath', '');

        $this->exec('reports index');

        $this->assertExitSuccess();
        $this->assertOutputContains('WPKG_REPORTS_PATH is not set');
        $this->assertSame(0, $this->fetchTable('ReportIndexState')->find()->count());
    }

    /**
     * An unreadable directory fails the pass, so that the loop logs it, and records the error.
     *
     * @return void
     */
    public function testUnreadableDirectoryFails(): void
    {
        Configure::write('Wpkg.reportsPath', $this->reportsPath . DS . 'missing');

        $this->exec('reports index');

        $this->assertExitError();
        $this->assertErrorContains('Reports directory cannot be read');
    }

    /**
     * A second pass started while one runs exits without doing anything, and the lock is released after a pass.
     *
     * @return void
     */
    public function testSecondPassWaitsForTheFirst(): void
    {
        ConnectionManager::setConfig('lock_holder', ConnectionManager::getConfig('test'));
        $holder = ConnectionManager::get('lock_holder');
        $holder->execute("SELECT GET_LOCK('wpkg_reports_index', 0)");

        $this->exec('reports index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Another pass is running');
        $this->assertSame(0, $this->fetchTable('HostReports')->find()->count());

        $holder->execute("SELECT RELEASE_LOCK('wpkg_reports_index')");
        $this->exec('reports index');

        $this->assertOutputContains('Files seen: 6');
        $isFree = $holder->execute("SELECT IS_FREE_LOCK('wpkg_reports_index')")->fetchColumn(0);
        $this->assertSame(1, (int)$isFree);
    }
}
