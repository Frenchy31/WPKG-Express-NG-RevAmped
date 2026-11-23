<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Cleanup\DatabaseCleanup;
use App\Model\Table\PackageChecksTable;
use App\Test\Support\AdminIntegrationTestCase;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Database cleanup: orphan counts on the Admin page, and the action deleting the orphan rows.
 */
class DatabaseCleanupControllerTest extends AdminIntegrationTestCase
{
    /**
     * Orphan rows inserted by `insertOrphans()`, by table. Each count differs, so a mix-up shows.
     */
    private const array COUNTS = [
        'exit_codes' => 1,
        'package_actions' => 2,
        'package_checks' => 3,
        'variables' => 4,
        'hosts_profiles' => 5,
        'packages_profiles' => 6,
        'packages_packages' => 7,
        'profiles_profiles' => 8,
        'hosts' => 9,
    ];

    /**
     * The Admin page counts the orphan rows of each table and lists the hosts without main profile.
     *
     * @return void
     */
    public function testAdminPageCountsOrphansPerTable(): void
    {
        $this->insertOrphans();

        $this->get('/admin');

        $this->assertResponseOk();
        $this->assertSame(self::COUNTS, $this->countsByTable('orphan-counts'));
        $this->assertSame(
            ['orphan-host-1', 'orphan-host-2', 'orphan-host-3', 'orphan-host-4', 'orphan-host-5',
                'orphan-host-6', 'orphan-host-7', 'orphan-host-8', 'orphan-host-9'],
            $this->cellTexts('//table[@id="hosts-without-main-profile"]//tr[td]/td[1]'),
        );
        $this->assertResponseContains('Delete 36 orphan rows? This cannot be undone.');
        $this->assertCount(1, $this->nodes('//form[@action="/database-cleanup/run"][@method="post"]'));
        $this->assertCount(1, $this->nodes('//form[@action="/database-cleanup/run"]//input[@name="_csrfToken"]'));

        $hostLink = $this->nodes('//table[@id="hosts-without-main-profile"]//tr[td]//a')->item(0);
        $this->get((string)$hostLink?->getAttribute('href'));
        $this->assertResponseOk('A host without main profile can be edited.');
    }

    /**
     * Without orphan, the page says so and offers no button.
     *
     * @return void
     */
    public function testAdminPageWithoutOrphans(): void
    {
        $this->get('/admin');

        $this->assertResponseOk();
        $this->assertSame(array_fill_keys(array_keys(self::COUNTS), 0), $this->countsByTable('orphan-counts'));
        $this->assertResponseContains('No orphan rows to delete.');
        $this->assertCount(0, $this->nodes('//form[@action="/database-cleanup/run"]'));
        $this->assertCount(0, $this->nodes('//table[@id="hosts-without-main-profile"]'));
    }

    /**
     * The cleanup deletes the orphan rows, keeps the hosts, and reports the rows deleted from each table.
     *
     * @return void
     */
    public function testCleanDeletesOrphansAndReportsRowsDeletedPerTable(): void
    {
        $this->insertOrphans();

        $this->post('/database-cleanup/run');

        $this->assertResponseOk();
        $this->assertResponseContains('36 orphan rows deleted.');
        $this->assertSame(array_diff_key(self::COUNTS, ['hosts' => 0]), $this->countsByTable('deleted-counts'));
        $this->assertCount(9, $this->nodes('//table[@id="hosts-without-main-profile"]//tr[td]'));

        $this->get('/admin');

        $this->assertSame(
            array_replace(array_fill_keys(array_keys(self::COUNTS), 0), ['hosts' => 9]),
            $this->countsByTable('orphan-counts'),
        );
        $this->assertResponseContains('No orphan rows to delete.');
    }

    /**
     * The feeds read by the clients are identical before and after the cleanup.
     *
     * @param bool $exportDisabled Whether disabled records are exported as comments.
     * @return void
     */
    #[TestWith([false])]
    #[TestWith([true])]
    public function testFeedsAreIdenticalBeforeAndAfterTheCleanup(bool $exportDisabled): void
    {
        Configure::write('Wpkg.xml.exportDisabled', $exportDisabled);
        $this->insertOrphans();
        $before = $this->feeds();

        $this->post('/database-cleanup/run');
        $this->assertResponseOk();

        $this->assertSame($before, $this->feeds());
        $this->assertStringContainsString('<package id="7zip"', $before['packages.xml']);
    }

    /**
     * Only POST runs the cleanup.
     *
     * @return void
     */
    public function testCleanRefusesGet(): void
    {
        $this->insertOrphans();

        $this->get('/database-cleanup/run');

        $this->assertResponseCode(405);
        $this->assertSame(36, $this->rowsToDelete());
    }

    /**
     * Only the logged-in administrator runs the cleanup.
     *
     * @return void
     */
    public function testCleanRequiresLogin(): void
    {
        $this->insertOrphans();
        $this->session(['Auth' => null]);

        $this->post('/database-cleanup/run');

        $this->assertRedirectContains('/login');
        $this->assertSame(36, $this->rowsToDelete());
    }

    /**
     * A request without CSRF token is rejected and deletes nothing.
     *
     * @return void
     */
    public function testCleanRequiresCsrfToken(): void
    {
        $this->insertOrphans();
        $this->_csrfToken = false;

        $this->post('/database-cleanup/run');

        $this->assertResponseCode(403);
        $this->assertSame(36, $this->rowsToDelete());
    }

    /**
     * A failed cleanup deletes nothing and sends back to the Admin page with the reason.
     *
     * @return void
     */
    public function testFailedCleanupShowsTheReasonAndDeletesNothing(): void
    {
        $this->insertOrphans();
        // The request builds its own table instances: only the global manager sees their events.
        $refuseCheckDelete = function (EventInterface $event): void {
            if ($event->getSubject() instanceof PackageChecksTable) {
                $event->setResult(false);
                $event->stopPropagation();
            }
        };
        EventManager::instance()->on('Model.beforeDelete', ['priority' => 1], $refuseCheckDelete);
        try {
            $this->post('/database-cleanup/run');
        } finally {
            EventManager::instance()->off('Model.beforeDelete', $refuseCheckDelete);
        }

        $this->assertRedirect('/admin');
        $this->assertFlashElement('flash/error');
        $this->assertMatchesRegularExpression(
            '/^Package check \d+ could not be deleted\.$/',
            (string)$this->_requestSession->read('Flash.flash.0.message'),
        );
        $this->assertSame(36, $this->rowsToDelete());
    }

    /**
     * Inserts the orphan rows counted by `COUNTS`: the reference data holds the ids up to 29, so 900 and above
     * are missing.
     *
     * @return void
     */
    private function insertOrphans(): void
    {
        $connection = ConnectionManager::get('test');
        for ($index = 1; $index <= self::COUNTS['exit_codes']; $index++) {
            $connection->execute(
                "INSERT INTO exit_codes (package_action_id, code, reboot) VALUES (?, '0', 0)",
                [900 + $index],
            );
        }
        for ($index = 1; $index <= self::COUNTS['package_actions']; $index++) {
            $connection->execute(
                "INSERT INTO package_actions (package_id, type, command, position) VALUES (?, 0, 'setup.exe', 1)",
                [900 + $index],
            );
        }
        for ($index = 1; $index <= self::COUNTS['package_checks']; $index++) {
            $connection->execute(
                'INSERT INTO package_checks (package_id, type, `condition`, lft, rght) VALUES (?, 3, 19, 1, 2)',
                [900 + $index],
            );
        }
        for ($index = 1; $index <= self::COUNTS['variables']; $index++) {
            $connection->execute(
                "INSERT INTO variables (ref_id, ref_type, name, value) VALUES (?, ?, 'NAME', 'value')",
                [900 + $index, $index % 3],
            );
        }
        $links = [
            'hosts_profiles' => ['host_id, profile_id', '?, 1'],
            'packages_profiles' => ['profile_id, package_id', '1, ?'],
            'packages_packages' => ['package_id, dependency_id', '10, ?'],
            'profiles_profiles' => ['profile_id, dependency_id', '3, ?'],
        ];
        foreach ($links as $table => [$columns, $values]) {
            for ($index = 1; $index <= self::COUNTS[$table]; $index++) {
                $connection->execute(
                    'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $values . ')',
                    [900 + $index],
                );
            }
        }
        for ($index = 1; $index <= self::COUNTS['hosts']; $index++) {
            $connection->execute(
                'INSERT INTO hosts (enabled, name, created, modified, mainprofile_id, position)
                    VALUES (1, ?, NOW(), NOW(), ?, ?)',
                ['orphan-host-' . $index, 900 + $index, 10 + $index],
            );
        }
    }

    /**
     * Counts the orphan rows the cleanup would delete.
     *
     * @return int
     */
    private function rowsToDelete(): int
    {
        return array_sum(array_diff_key((new DatabaseCleanup())->orphanCounts(), ['hosts' => 0]));
    }

    /**
     * Fetches the three feeds.
     *
     * @return array<string, string>
     */
    private function feeds(): array
    {
        $feeds = [];
        foreach (['hosts.xml', 'profiles.xml', 'packages.xml'] as $feed) {
            $this->get('/' . $feed);
            $this->assertResponseOk();
            $feeds[$feed] = (string)$this->_response->getBody();
        }

        return $feeds;
    }

    /**
     * Reads a table of the last response as table name => number in its last column.
     *
     * @param string $tableId Id of the HTML table.
     * @return array<string, int>
     */
    private function countsByTable(string $tableId): array
    {
        $counts = [];
        foreach ($this->nodes('//table[@id="' . $tableId . '"]//tr[td]') as $row) {
            $cells = $row->getElementsByTagName('td');
            $name = trim((string)$cells->item(0)?->textContent);
            $counts[$name] = (int)trim((string)$cells->item($cells->length - 1)?->textContent);
        }

        return $counts;
    }

    /**
     * Reads the text of the nodes an XPath expression selects in the last response.
     *
     * @param string $expression XPath expression.
     * @return list<string>
     */
    private function cellTexts(string $expression): array
    {
        $texts = [];
        foreach ($this->nodes($expression) as $node) {
            $texts[] = trim($node->textContent);
        }

        return $texts;
    }

    /**
     * Selects nodes of the last response.
     *
     * @param string $expression XPath expression.
     * @return \DOMNodeList<\DOMNode>
     */
    private function nodes(string $expression): \DOMNodeList
    {
        $document = new DOMDocument();
        $document->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR);

        return (new DOMXPath($document))->query($expression);
    }
}
