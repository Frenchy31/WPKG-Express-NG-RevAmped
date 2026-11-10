<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use App\Test\Support\ReportFixturesTrait;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Fleet dashboard of the home page.
 */
class HomeControllerTest extends AdminIntegrationTestCase
{
    use LocatorAwareTrait;
    use ReportFixturesTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $reportsPath = $this->copyReportFixtures();
        // The fixture logs date from 2026-11-08: move them far enough back to be stale whenever the tests run.
        foreach ((array)glob($reportsPath . DS . '*.log') as $logPath) {
            $content = str_replace('2026-11-08', '2020-01-01', (string)file_get_contents((string)$logPath));
            file_put_contents((string)$logPath, $content);
        }
        Configure::write('Wpkg.reportsPath', $reportsPath);
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
     * The dashboard is French with the wpkg_lang cookie, plural forms included.
     *
     * @return void
     */
    public function testFrenchDashboard(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains(
            '1 poste a échoué lors de sa dernière exécution. 1 s&#039;est terminé avec un avertissement. '
            . '2 n&#039;ont aucun rapport lisible. 3 n&#039;ont pas envoyé de rapport depuis plus de 24 heures.',
        );
        $this->assertResponseContains('Dernier rapport');
        $this->assertResponseContains('aria-label="Échec 1, Avertissement 1, Inconnu 2, Succès 1"');
        $this->assertResponseContains('<h2>Postes</h2>');
        $this->assertResponseContains('<h2>À traiter</h2>');
        $this->assertResponseContains('Voir tous les rapports');
        $this->assertResponseContains('Ancien 3');
        $this->assertResponseContains('en échec');
        $this->assertResponseContains('dernière exécution');
        $this->assertResponseNotContains('Needs attention');
    }

    /**
     * The sentence, the bar and the legend carry the totals of the reports page.
     *
     * @return void
     */
    public function testStatusSentenceBarAndLegend(): void
    {
        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains(
            '1 workstation failed its last run. 1 finished with a warning. 2 have no readable report. '
            . '3 have not reported for more than 24 hours.',
        );
        $this->assertResponseContains('Latest report: ');
        $this->assertResponseContains('aria-label="Failed 1, Warning 1, Unknown 2, Success 1"');
        $this->assertResponseContains('<rect class="status-failed" x="0" y="0" width="20" height="8" />');
        $this->assertResponseContains('Stale 3');
        $this->get('/reports');
        $this->assertResponseContains('Failed: 1');
        $this->assertResponseContains('Stale: 3');
    }

    /**
     * Each workstation is one cell linking to its log, described without the color.
     *
     * @return void
     */
    public function testRoomMap(): void
    {
        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains('<h2>Workstations</h2>');
        $this->assertSame(5, substr_count((string)$this->_response->getBody(), 'class="fleet-cell '));
        foreach (['BROKEN', 'DEV-7', 'ROOM1-PC01', 'ROOM1-PC02', 'ROOM2-PC05'] as $hostname) {
            $this->assertResponseContains('href="/reports/' . $hostname . '"');
        }
        $this->assertResponseContains(
            'aria-label="ROOM1-PC02: Failed, 1 error, last run 2020-01-01 03:00:00 AM (more than 24 hours ago)"',
        );
        $this->assertResponseContains('fleet-cell status-warning stale');
        $this->assertResponseContains('room1-pc.+');
        $this->assertResponseContains('2 hosts, 1 failed');
    }

    /**
     * Needs attention lists the failure first, with its cause and a link to all reports.
     *
     * @return void
     */
    public function testNeedsAttention(): void
    {
        $this->get('/');

        $this->assertResponseContains('<h2>Needs attention</h2>');
        $this->assertResponseContains('Could not process (install) 7-Zip. Failed checking after installation.');
        $this->assertResponseContains('No readable log');
        $this->assertResponseContains('No report for ');
        $this->assertResponseContains('href="/reports">Show all reports</a>');
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, 'ROOM2-PC05</a>'), strpos($body, 'ROOM1-PC02</a>'));
    }

    /**
     * The page orders Summary, Workstations, Needs attention, then the configuration blocks.
     *
     * @return void
     */
    public function testSectionOrder(): void
    {
        $this->get('/');

        $body = (string)$this->_response->getBody();
        $positions = array_map(
            fn(string $title): int|false => strpos($body, '<h2>' . $title . '</h2>'),
            ['Summary', 'Workstations', 'Needs attention', 'Packages', 'Profiles', 'Hosts'],
        );
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertResponseContains('# Enabled');
    }

    /**
     * A fleet that ran today has nothing to fix.
     *
     * @return void
     */
    public function testHealthyFleet(): void
    {
        foreach ((array)glob($this->reportsPath . DS . '*') as $file) {
            unlink((string)$file);
        }
        file_put_contents(
            $this->reportsPath . DS . 'ROOM1-PC01.log',
            date('Y-m-d H:i:s') . ", DEBUG   : WPKG 1.3.1 starting...\n" . date('Y-m-d H:i:s') . ", INFO    : Done.\n",
        );
        $this->indexReportFixtures();

        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains('1 workstation finished its last run without errors.');
        $this->assertResponseContains('Nothing to fix: every workstation succeeded in the last 24 hours.');
    }

    /**
     * Without a reports directory, only the message and the configuration blocks remain.
     *
     * @return void
     */
    public function testNotConfigured(): void
    {
        Configure::write('Wpkg.reportsPath', '');

        $this->get('/');

        $this->assertResponseOk();
        $this->assertResponseContains(
            'Workstation reports are off. Set WPKG_REPORTS_PATH to show the last run of each host.',
        );
        $this->assertResponseNotContains('fleet-');
        $this->assertResponseNotContains('Needs attention');
        $this->assertResponseContains('# Enabled');
    }

    /**
     * The dashboard comes from the index: it renders the same when the directory cannot be opened.
     *
     * @return void
     */
    public function testUnreadableDirectory(): void
    {
        Configure::write('Wpkg.reportsPath', $this->reportsPath . DS . 'missing');
        foreach ((array)glob($this->reportsPath . DS . '*') as $file) {
            unlink((string)$file);
        }
        rmdir($this->reportsPath);

        $this->get('/');

        $this->assertResponseOk();
        $this->assertSame(5, substr_count((string)$this->_response->getBody(), 'class="fleet-cell '));
        $this->assertResponseContains('1 workstation failed its last run.');
        $this->assertResponseNotContains('Reports directory cannot be read');
        $this->assertResponseContains('# Enabled');
        mkdir($this->reportsPath);
    }

    /**
     * The dashboard shows the end of the last pass, and warns when the indexer stopped or never ran.
     *
     * @return void
     */
    public function testIndexerStatus(): void
    {
        $this->get('/');
        $this->assertResponseContains('Last indexing: ');
        $this->assertResponseNotContains('has not run since');

        $this->fetchTable('ReportIndexState')->updateAll(['finished_at' => time() - 3600], ['id' => 1]);
        $this->get('/');
        $this->assertResponseContains('The reports indexer has not run since');
        $this->assertResponseContains('fleet-summary');

        $this->fetchTable('ReportIndexState')->deleteAll([]);
        $this->fetchTable('HostReports')->deleteAll([]);
        $this->get('/');
        $this->assertResponseContains('The reports indexer has never run');
        $this->assertResponseContains('No reports found');
    }

    /**
     * The warning is French with the wpkg_lang cookie.
     *
     * @return void
     */
    public function testIndexerWarningInFrench(): void
    {
        $this->cookie('wpkg_lang', 'fr');
        $this->fetchTable('ReportIndexState')->deleteAll([]);

        $this->get('/');

        $this->assertResponseContains("L'indexeur des rapports n'a jamais tourné");
    }

    /**
     * An empty directory is reported as on the reports page.
     *
     * @return void
     */
    public function testEmptyDirectory(): void
    {
        foreach ((array)glob($this->reportsPath . DS . '*') as $file) {
            unlink((string)$file);
        }
        $this->indexReportFixtures();

        $this->get('/');

        $this->assertResponseContains('No reports found');
        $this->assertResponseNotContains('fleet-');
        $this->assertResponseContains('# Enabled');
    }
}
