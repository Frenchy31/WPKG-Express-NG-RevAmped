<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use App\Test\Support\ReportFixturesTrait;
use Cake\Core\Configure;
use Cake\I18n\I18n;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Fleet dashboard of the home page.
 */
class HomeControllerTest extends AdminIntegrationTestCase
{
    use LocatorAwareTrait;
    use ReportFixturesTrait;

    private const string ALL_STATUSES = 'filter=1&status[]=success&status[]=failed&status[]=warning'
        . '&status[]=unknown&status[]=stale';

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
        // A French request leaves its locale behind, and later test classes compare English labels.
        I18n::setLocale((string)Configure::read('App.defaultLocale'));
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
        $this->assertResponseContains('Filtrer</button>');
        $this->assertResponseContains('Succès 1');
        $this->assertResponseContains('Aucun rapport depuis plus de 24 heures 3');
        $this->assertResponseNotContains('Aucun poste ne correspond');
        $this->assertResponseNotContains('Needs attention');
    }

    /**
     * Workstations running the agent come after the rooms and before the single workstations.
     *
     * @return void
     */
    public function testAgentGroupOrder(): void
    {
        $this->writeAgentReport('DEV-7');
        $this->indexReportFixtures();

        $this->get('/?' . self::ALL_STATUSES);

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $positions = array_map(
            fn(string $label): int|false => strpos($body, '<strong>' . $label . '</strong>'),
            ['room1-pc.+', 'WPKG agent', 'room2-pc.+', '.+'],
        );
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertResponseNotContains('<strong>^dev-[0-9]+$</strong>');
    }

    /**
     * The agent group is translated.
     *
     * @return void
     */
    public function testFrenchAgentGroup(): void
    {
        $this->writeAgentReport('DEV-7');
        $this->indexReportFixtures();
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/?' . self::ALL_STATUSES);

        $this->assertResponseContains('<strong>Agent WPKG</strong>');
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
        $this->get('/?' . self::ALL_STATUSES);

        $this->assertResponseOk();
        $this->assertResponseContains('<h2>Workstations</h2>');
        $this->assertSame(5, substr_count((string)$this->_response->getBody(), 'class="fleet-cell '));
        foreach (['DEV-7', 'BROKEN', 'ROOM1-PC01', 'ROOM1-PC02', 'ROOM2-PC05'] as $hostname) {
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
     * Hostnames of the cells of the last response, in page order.
     *
     * @return list<string>
     */
    private function cellHostnames(): array
    {
        $pattern = '#class="fleet-cell [^"]*" href="/reports/([^"]+)"#';
        preg_match_all($pattern, (string)$this->_response->getBody(), $matches);

        return $matches[1];
    }

    /**
     * Without parameter the map shows the failed and warning workstations, and checks their boxes.
     *
     * @return void
     */
    public function testDefaultFilter(): void
    {
        $this->get('/');

        $this->assertResponseOk();
        $this->assertSame(['ROOM1-PC02', 'ROOM2-PC05'], $this->cellHostnames());
        $this->assertResponseContains('<input type="hidden" name="filter" value="1" />');
        $this->assertResponseContains('name="status[]" value="failed" checked />');
        $this->assertResponseContains('name="status[]" value="warning" checked />');
        $this->assertResponseContains('name="status[]" value="success" />');
        $this->assertResponseContains('name="status[]" value="unknown" />');
        $this->assertResponseContains('name="status[]" value="stale" />');
        $this->assertResponseContains('Filter</button>');
        $this->assertResponseNotContains('No workstation matches');
        $this->assertSame(0, substr_count((string)$this->_response->getBody(), ' style="'));
    }

    /**
     * The key is the filter: five labelled boxes in order, with the counts of the whole fleet.
     *
     * @return void
     */
    public function testFilterEntries(): void
    {
        $this->get('/');

        preg_match('#<form class="fleet-filter".*?</form>#s', (string)$this->_response->getBody(), $form);
        $body = $form[0];
        $this->assertSame(5, substr_count($body, '<label>'));
        $this->assertSame(5, substr_count($body, '<input type="checkbox" name="status[]" value="'));
        $entries = [
            'value="success" /><span class="fleet-swatch status-success"></span>Success 1',
            'value="failed" checked /><span class="fleet-swatch status-failed"></span>✕ Failed 1',
            'value="warning" checked /><span class="fleet-swatch status-warning"></span>! Warning 1',
            'value="unknown" /><span class="fleet-swatch status-unknown"></span>? Unknown 2',
            'value="stale" /><span class="fleet-swatch stale"></span>No report for more than 24 hours 3',
        ];
        $positions = array_map(
            fn(string $entry): int|false => strpos((string)preg_replace('/>\s+</', '><', $body), $entry),
            $entries,
        );
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    /**
     * Each status alone shows its workstations only.
     *
     * @return void
     */
    public function testEachStatusAlone(): void
    {
        $expected = [
            'success' => ['ROOM1-PC01'],
            'failed' => ['ROOM1-PC02'],
            'warning' => ['ROOM2-PC05'],
            'unknown' => ['DEV-7', 'BROKEN'],
        ];
        foreach ($expected as $status => $hostnames) {
            $this->get('/?filter=1&status[]=' . $status);

            $this->assertResponseOk();
            $this->assertSame($hostnames, $this->cellHostnames(), $status);
            $this->assertResponseContains('value="' . $status . '" checked />');
        }
    }

    /**
     * Stale adds the workstations that did not report, whatever their status, to the checked statuses.
     *
     * @return void
     */
    public function testStaleAlone(): void
    {
        $this->get('/?filter=1&status[]=stale');

        $this->assertSame(['ROOM1-PC01', 'ROOM1-PC02', 'ROOM2-PC05'], $this->cellHostnames());
    }

    /**
     * Stale is added to a status, not intersected with it.
     *
     * @return void
     */
    public function testStaleWithStatus(): void
    {
        $this->get('/?filter=1&status[]=unknown&status[]=stale');

        $this->assertSame(
            ['ROOM1-PC01', 'ROOM1-PC02', 'ROOM2-PC05', 'DEV-7', 'BROKEN'],
            $this->cellHostnames(),
        );
    }

    /**
     * A failed workstation whose report is old stays on the default map, a successful one is hidden.
     *
     * @return void
     */
    public function testDefaultShowsStaleFailureAndHidesStaleSuccess(): void
    {
        $this->get('/');

        $this->assertResponseContains(
            'aria-label="ROOM1-PC02: Failed, 1 error, last run 2020-01-01 03:00:00 AM (more than 24 hours ago)"',
        );
        $this->assertResponseContains('fleet-cell status-failed stale');
        $this->assertNotContains('ROOM1-PC01', $this->cellHostnames());
    }

    /**
     * A group without visible workstation disappears, the others keep their whole counts.
     *
     * @return void
     */
    public function testGroupsKeepOrderAndWholeCounts(): void
    {
        $this->get('/');

        $this->assertResponseContains('<strong>room1-pc.+</strong>');
        $this->assertResponseContains('<strong>room2-pc.+</strong>');
        $this->assertResponseNotContains('<strong>.+</strong>');
        $this->assertResponseContains('2 hosts, 1 failed');
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(
            strpos($body, '<strong>room2-pc.+</strong>'),
            strpos($body, '<strong>room1-pc.+</strong>'),
        );
    }

    /**
     * Nothing matches: a message replaces the map.
     *
     * @return void
     */
    public function testNoMatchMessage(): void
    {
        $this->get('/?filter=1&status[]=success&status[]=unknown');
        $this->assertSame(['ROOM1-PC01', 'DEV-7', 'BROKEN'], $this->cellHostnames());
        $this->assertResponseNotContains('No workstation matches');

        foreach (['/?filter=1&status[]=nothing', '/?filter=1'] as $url) {
            $this->get($url);

            $this->assertResponseOk();
            $this->assertSame([], $this->cellHostnames());
            $this->assertResponseNotContains('fleet-group');
            $this->assertResponseContains('<p>No workstation matches the selected statuses.</p>');
        }
    }

    /**
     * Submitting the form with nothing checked does not bring the default back.
     *
     * @return void
     */
    public function testNothingCheckedIsNotTheDefault(): void
    {
        $this->get('/?filter=1');

        $this->assertResponseNotContains('checked');
        $this->assertResponseContains('No workstation matches the selected statuses.');
    }

    /**
     * An unknown value is ignored, a known one next to it still applies.
     *
     * @return void
     */
    public function testUnknownValueIgnored(): void
    {
        $this->get('/?filter=1&status[]=bogus&status[]=failed&status[]=Stale');

        $this->assertResponseOk();
        $this->assertSame(['ROOM1-PC02'], $this->cellHostnames());
        $this->assertResponseNotContains('value="stale" checked');
        $this->assertResponseNotContains('bogus');
    }

    /**
     * A scalar status parameter is read as one value.
     *
     * @return void
     */
    public function testScalarStatusParameter(): void
    {
        $this->get('/?filter=1&status=warning');

        $this->assertSame(['ROOM2-PC05'], $this->cellHostnames());
    }

    /**
     * The sentence, the bar, its legend and the attention list ignore the filter.
     *
     * @return void
     */
    public function testSummaryIgnoresFilter(): void
    {
        $extract = function (): array {
            $body = (string)$this->_response->getBody();
            preg_match('#<div class="fleet-summary">.*?</ul>#s', $body, $summary);
            preg_match('#<section class="fleet-attention">.*?</section>#s', $body, $attention);

            return [$summary[0], $attention[0]];
        };
        $this->get('/');
        $default = $extract();
        foreach (['/?filter=1', '/?' . self::ALL_STATUSES, '/?filter=1&status[]=success'] as $url) {
            $this->get($url);

            $this->assertSame($default, $extract(), $url);
        }
        $this->assertStringContainsString('1 workstation failed its last run.', $default[0]);
    }

    /**
     * The filter texts are French with the wpkg_lang cookie.
     *
     * @return void
     */
    public function testFrenchFilter(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/?filter=1');

        $this->assertResponseContains('Filtrer</button>');
        $this->assertResponseContains('<p>Aucun poste ne correspond aux statuts choisis.</p>');
        $this->assertResponseContains('✕ Échec 1');
        $this->assertResponseNotContains('No workstation matches');
    }

    /**
     * Without reports directory there is no filter.
     *
     * @return void
     */
    public function testNoFilterWhenNotConfigured(): void
    {
        Configure::write('Wpkg.reportsPath', '');

        $this->get('/?filter=1');

        $this->assertResponseNotContains('status[]');
        $this->assertResponseNotContains('Filter</button>');
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
        $this->assertSame(2, substr_count((string)$this->_response->getBody(), 'class="fleet-cell '));
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
