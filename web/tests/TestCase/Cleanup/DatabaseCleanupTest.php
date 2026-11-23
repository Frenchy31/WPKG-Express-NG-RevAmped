<?php

declare(strict_types=1);

namespace App\Test\TestCase\Cleanup;

use App\Cleanup\DatabaseCleanup;
use App\Cleanup\OrphanTable;
use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use App\Test\Support\ReferenceDataTrait;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Detection and deletion of the rows pointing to records that do not exist.
 *
 * The reference data holds the ids 1 to 4 for profiles, 1 to 5 for hosts, 1 to 11 for packages and 1 to 20
 * for package actions. Orphans point to the id 999. Package 11 has no check in the reference data.
 */
class DatabaseCleanupTest extends TestCase
{
    use LocatorAwareTrait;
    use ReferenceDataTrait;

    private const array TABLES = [
        'exit_codes', 'hosts', 'hosts_profiles', 'package_actions', 'package_checks', 'packages',
        'packages_packages', 'packages_profiles', 'profiles', 'profiles_profiles', 'variables',
    ];

    private DatabaseCleanup $cleanup;

    private Connection $connection;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');
        $this->connection = $connection;
        $this->cleanup = new DatabaseCleanup();
    }

    /**
     * Lists one orphan row per column that must point to an existing record.
     *
     * @return array<string, array{string, string}> Table the cleanup reports it under, statement inserting it.
     */
    public static function orphanRows(): array
    {
        $check = 'INSERT INTO package_checks (package_id, type, `condition`, parent_id, lft, rght) VALUES ';
        $variable = "INSERT INTO variables (ref_id, ref_type, name, value) VALUES (%d, %d, 'NAME', 'value')";

        return [
            'hosts_profiles, host missing' => [
                'hosts_profiles', 'INSERT INTO hosts_profiles (host_id, profile_id) VALUES (999, 1)',
            ],
            'hosts_profiles, profile missing' => [
                'hosts_profiles', 'INSERT INTO hosts_profiles (host_id, profile_id) VALUES (1, 999)',
            ],
            'packages_profiles, package missing' => [
                'packages_profiles', 'INSERT INTO packages_profiles (profile_id, package_id) VALUES (1, 999)',
            ],
            'packages_profiles, profile missing' => [
                'packages_profiles', 'INSERT INTO packages_profiles (profile_id, package_id) VALUES (999, 1)',
            ],
            'packages_packages, package missing' => [
                'packages_packages', 'INSERT INTO packages_packages (package_id, dependency_id) VALUES (999, 1)',
            ],
            'packages_packages, dependency missing' => [
                'packages_packages', 'INSERT INTO packages_packages (package_id, dependency_id) VALUES (10, 999)',
            ],
            'profiles_profiles, profile missing' => [
                'profiles_profiles', 'INSERT INTO profiles_profiles (profile_id, dependency_id) VALUES (999, 1)',
            ],
            'profiles_profiles, dependency missing' => [
                'profiles_profiles', 'INSERT INTO profiles_profiles (profile_id, dependency_id) VALUES (3, 999)',
            ],
            'package_actions, package missing' => [
                'package_actions',
                "INSERT INTO package_actions (package_id, type, command, position) VALUES (999, 0, 'setup.exe', 1)",
            ],
            'exit_codes, action missing' => [
                'exit_codes', "INSERT INTO exit_codes (package_action_id, code, reboot) VALUES (999, '0', 0)",
            ],
            'package_checks, package missing' => ['package_checks', $check . '(999, 3, 19, NULL, 1, 2)'],
            'package_checks, parent missing' => ['package_checks', $check . '(11, 3, 19, 999, 1, 2)'],
            'package_checks, parent in another package' => ['package_checks', $check . '(11, 3, 19, 2, 1, 2)'],
            'package_checks, outside the nested set' => ['package_checks', $check . '(999, 3, 19, NULL, NULL, NULL)'],
            'variables, package missing' => ['variables', sprintf($variable, 999, 0)],
            'variables, profile missing' => ['variables', sprintf($variable, 999, 1)],
            'variables, host missing' => ['variables', sprintf($variable, 999, 2)],
            'variables, id of another type only' => ['variables', sprintf($variable, 5, 1)],
            'hosts, main profile missing' => [
                'hosts',
                "INSERT INTO hosts (enabled, name, created, modified, mainprofile_id, position)
                    VALUES (1, 'orphan-pc', NOW(), NOW(), 999, 9)",
            ],
        ];
    }

    /**
     * The reference data holds no orphan, and the nested sets of its packages are valid.
     *
     * @return void
     */
    public function testReferenceDataHasNoOrphan(): void
    {
        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
        $this->assertSame([], $this->cleanup->hostsWithoutMainProfile());
        $this->assertNestedSetsAreValid();
    }

    /**
     * Each orphan is counted under its table only, deleted, and nothing else changes.
     *
     * @param string $table Table the cleanup reports the orphan under.
     * @param string $statement Statement inserting the orphan.
     * @return void
     */
    #[DataProvider('orphanRows')]
    public function testOrphanIsCountedThenDeleted(string $table, string $statement): void
    {
        $before = $this->snapshot();
        $this->connection->execute($statement);

        $this->assertSame($this->counts([$table => 1]), $this->cleanup->orphanCounts());

        $deleted = $this->cleanup->clean();

        $isHost = $table === 'hosts';
        $this->assertSame($this->deleted($isHost ? [] : [$table => 1]), $deleted);
        $this->assertSame($this->counts($isHost ? ['hosts' => 1] : []), $this->cleanup->orphanCounts());
        $after = $this->snapshot();
        if ($isHost) {
            $this->assertCount(count($before['hosts']) + 1, $after['hosts']);
            unset($before['hosts'], $after['hosts']);
        }
        $this->assertSame($before, $after);
    }

    /**
     * Every kind of orphan together: counted exactly, deleted in one go, valid rows and feeds untouched.
     *
     * @return void
     */
    public function testEveryKindOfOrphanTogether(): void
    {
        $before = $this->snapshot();
        $feeds = $this->feeds();
        $expected = [];
        foreach (self::orphanRows() as [$table, $statement]) {
            if ($table === 'hosts') {
                continue;
            }
            $this->connection->execute($statement);
            $expected[$table] = ($expected[$table] ?? 0) + 1;
        }

        $this->assertSame($this->counts($expected), $this->cleanup->orphanCounts());
        $this->assertSame($feeds, $this->feeds(), 'Orphans are invisible in the feeds.');

        $this->assertSame($this->deleted($expected), $this->cleanup->clean());

        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($feeds, $this->feeds());
        $this->assertNestedSetsAreValid();
    }

    /**
     * The exit codes of an action whose package is missing go with the action, counted before the cleanup.
     *
     * @return void
     */
    public function testExitCodesOfAnOrphanActionAreCountedAndDeleted(): void
    {
        $this->connection->execute(
            "INSERT INTO package_actions (id, package_id, type, command, position) VALUES (500, 999, 0, 'a.exe', 1)",
        );
        $this->connection->execute(
            "INSERT INTO exit_codes (package_action_id, code, reboot)
                VALUES (500, '0', 0), (500, '1', 1), (501, '2', 0)",
        );

        $this->assertSame(
            $this->counts(['exit_codes' => 3, 'package_actions' => 1]),
            $this->cleanup->orphanCounts(),
        );

        $this->assertSame($this->deleted(['exit_codes' => 3, 'package_actions' => 1]), $this->cleanup->clean());
        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
    }

    /**
     * A variable belongs to the table its ref_type names. An unknown type is not an orphan: it is left alone.
     *
     * @return void
     */
    public function testVariablesFollowTheirRefType(): void
    {
        $this->connection->execute(
            "INSERT INTO variables (ref_id, ref_type, name, value) VALUES
                (11, 0, 'A', '1'), (4, 1, 'B', '1'), (5, 2, 'C', '1'), (999, 9, 'D', '1'), (5, 1, 'E', '1')",
        );

        $this->assertSame($this->counts(['variables' => 1]), $this->cleanup->orphanCounts());

        $this->assertSame($this->deleted(['variables' => 1]), $this->cleanup->clean());
        $names = $this->connection
            ->execute("SELECT name FROM variables WHERE name IN ('A', 'B', 'C', 'D', 'E') ORDER BY name")
            ->fetchAll('num');
        $this->assertSame([['A'], ['B'], ['C'], ['D']], $names);
    }

    /**
     * The checks below an orphan go with it: counted before the cleanup, and the checks after it are renumbered.
     *
     * @return void
     */
    public function testChecksBelowAnOrphanCheckAreCountedAndDeleted(): void
    {
        $this->insertChecks([
            [101, 11, null, 1, 4], [102, 11, 101, 2, 3],
            [103, 11, 999, 5, 10], [104, 11, 103, 6, 9], [105, 11, 104, 7, 8],
            [106, 11, null, 11, 12],
        ]);
        $feeds = $this->feeds();

        $this->assertSame($this->counts(['package_checks' => 3]), $this->cleanup->orphanCounts());

        $this->assertSame($this->deleted(['package_checks' => 3]), $this->cleanup->clean());

        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
        $this->assertSame($feeds, $this->feeds());
        $this->assertSame(
            [[101, 1, 4], [102, 2, 3], [106, 5, 6]],
            $this->connection->execute('SELECT id, lft, rght FROM package_checks WHERE package_id = 11 ORDER BY lft')
                ->fetchAll('num'),
        );
        $this->assertNestedSetsAreValid();
    }

    /**
     * An orphan inside the tree of a real package leaves that tree valid and its feed unchanged.
     *
     * @return void
     */
    public function testOrphanInsideTheTreeOfAPackageLeavesItValid(): void
    {
        $feeds = $this->feeds();
        $checks = $this->fetchTable('PackageChecks');
        /** @var \App\Model\Entity\PackageCheck $check */
        $check = $checks->newEntity(['type' => 2, 'condition' => 7, 'path' => 'C:\\new.txt', 'parent_id' => 5]);
        $check->set('package_id', 2);
        $this->assertTrue($checks->saveInPackage($check));
        $this->assertNotSame($feeds, $this->feeds(), 'A valid check shows in the feed.');
        $this->assertNestedSetsAreValid();
        $this->connection->execute('UPDATE package_checks SET parent_id = 999 WHERE id = ?', [$check->id]);

        $this->assertSame($feeds, $this->feeds(), 'An orphan check does not.');
        $this->assertSame($this->counts(['package_checks' => 1]), $this->cleanup->orphanCounts());
        $this->assertSame($this->deleted(['package_checks' => 1]), $this->cleanup->clean());

        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
        $this->assertSame($feeds, $this->feeds());
        $this->assertSame(29, $this->connection->execute('SELECT COUNT(*) FROM package_checks')->fetchColumn(0));
        $this->assertNestedSetsAreValid();
    }

    /**
     * A check outside the nested set is deleted, and so are the checks below it.
     *
     * @return void
     */
    public function testChecksBelowACheckOutsideTheNestedSetAreDeleted(): void
    {
        $this->insertChecks([[201, 11, 999, null, null], [202, 11, 201, 1, 2]]);

        $this->assertSame($this->counts(['package_checks' => 2]), $this->cleanup->orphanCounts());
        $this->assertSame($this->deleted(['package_checks' => 2]), $this->cleanup->clean());
        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
    }

    /**
     * A parent_id of 0 is a root, as in the feed: such a check is not an orphan and stays in the feed.
     *
     * @return void
     */
    public function testCheckWithParentIdZeroIsARoot(): void
    {
        $pristine = $this->feeds();
        $this->insertChecks([[301, 11, 0, 1, 2]]);
        $feeds = $this->feeds();

        $this->assertNotSame($pristine, $feeds, 'The check shows in the feed.');
        $this->assertSame($this->counts([]), $this->cleanup->orphanCounts());
        $this->assertSame($this->deleted([]), $this->cleanup->clean());
        $this->assertSame($feeds, $this->feeds());
        $this->assertSame(
            1,
            $this->connection->execute('SELECT COUNT(*) FROM package_checks WHERE id = 301')->fetchColumn(0),
        );
    }

    /**
     * Hosts without main profile are listed in feed order and never deleted.
     *
     * @return void
     */
    public function testHostsWithoutMainProfileAreListedAndKept(): void
    {
        $this->connection->execute(
            "INSERT INTO hosts (id, enabled, name, created, modified, mainprofile_id, position) VALUES
                (50, 1, 'second-pc', NOW(), NOW(), 998, 9), (51, 1, 'first-pc', NOW(), NOW(), 999, 8)",
        );
        $feeds = $this->feeds();

        $expected = [
            ['id' => 51, 'name' => 'first-pc', 'mainprofile_id' => 999],
            ['id' => 50, 'name' => 'second-pc', 'mainprofile_id' => 998],
        ];
        $this->assertSame($expected, $this->cleanup->hostsWithoutMainProfile());
        $this->assertSame($this->counts(['hosts' => 2]), $this->cleanup->orphanCounts());

        $this->assertSame($this->deleted([]), $this->cleanup->clean());

        $this->assertSame($expected, $this->cleanup->hostsWithoutMainProfile());
        $this->assertSame($feeds, $this->feeds());
    }

    /**
     * One failure undoes the whole cleanup.
     *
     * @return void
     */
    public function testFailureLeavesEveryTableUntouched(): void
    {
        $this->connection->execute("INSERT INTO exit_codes (package_action_id, code, reboot) VALUES (999, '0', 0)");
        $this->connection->execute("INSERT INTO variables (ref_id, ref_type, name, value) VALUES (999, 0, 'A', '1')");
        $this->insertChecks([[401, 999, null, 1, 2]]);
        $before = $this->snapshot();
        $this->fetchTable('PackageChecks')->getEventManager()->on(
            'Model.beforeDelete',
            ['priority' => 1],
            function (EventInterface $event): void {
                $event->setResult(false);
                $event->stopPropagation();
            },
        );

        try {
            $this->cleanup->clean();
            $this->fail('The cleanup must fail when a check cannot be deleted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Package check 401 could not be deleted.', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(
            $this->counts(['exit_codes' => 1, 'variables' => 1, 'package_checks' => 1]),
            $this->cleanup->orphanCounts(),
        );
    }

    /**
     * Builds the expected result of a count, zero for every table not given.
     *
     * @param array<string, int> $counts Count by table.
     * @return array<string, int>
     */
    private function counts(array $counts): array
    {
        $expected = [];
        foreach (OrphanTable::cases() as $table) {
            $expected[$table->value] = $counts[$table->value] ?? 0;
        }

        return $expected;
    }

    /**
     * Builds the expected result of a cleanup: the cleaned tables only, zero for those not given.
     *
     * @param array<string, int> $deleted Rows deleted by table.
     * @return array<string, int>
     */
    private function deleted(array $deleted): array
    {
        return array_diff_key($this->counts($deleted), ['hosts' => 0]);
    }

    /**
     * Inserts checks of the given shape.
     *
     * @param list<array{int, int, int|null, int|null, int|null}> $checks Id, package id, parent id, lft, rght.
     * @return void
     */
    private function insertChecks(array $checks): void
    {
        foreach ($checks as $check) {
            $this->connection->execute(
                'INSERT INTO package_checks (id, package_id, type, `condition`, parent_id, lft, rght)
                    VALUES (?, ?, 3, 19, ?, ?, ?)',
                $check,
            );
        }
    }

    /**
     * Reads every row of the tables the cleanup looks at.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $snapshot[$table] = $this->connection->execute('SELECT * FROM `' . $table . '` ORDER BY id')
                ->fetchAll('assoc');
        }

        return $snapshot;
    }

    /**
     * Renders the three feeds, with and without the disabled records.
     *
     * @return array<string, string>
     */
    private function feeds(): array
    {
        $feeds = [];
        foreach ([false, true] as $exportDisabled) {
            $feeds['hosts' . (int)$exportDisabled] = (new HostsFeed(true, $exportDisabled))->document();
            $feeds['profiles' . (int)$exportDisabled] = (new ProfilesFeed(true, $exportDisabled))->document();
            $feeds['packages' . (int)$exportDisabled] = (new PackagesFeed(true, $exportDisabled))->document();
        }

        return $feeds;
    }

    /**
     * Asserts that the checks of every package form a nested set without gap or overlap.
     *
     * Sibling moves rely on it: the Tree behavior looks for a neighbour at lft - 1 and rght + 1.
     *
     * @return void
     */
    private function assertNestedSetsAreValid(): void
    {
        $rows = $this->connection
            ->execute('SELECT id, package_id, parent_id, lft, rght FROM package_checks')
            ->fetchAll('assoc');
        $checksByPackage = [];
        foreach ($rows as $row) {
            $checksByPackage[(int)$row['package_id']][(int)$row['id']] = array_map('intval', $row);
        }

        foreach ($checksByPackage as $packageId => $checks) {
            $numbers = [];
            foreach ($checks as $check) {
                $this->assertLessThan($check['rght'], $check['lft'], 'Check ' . $check['id']);
                array_push($numbers, $check['lft'], $check['rght']);
            }
            sort($numbers);
            $this->assertSame(
                range($numbers[0], $numbers[0] + count($numbers) - 1),
                $numbers,
                'Package ' . $packageId . ' has a gap or a duplicate in its numbering.',
            );
            foreach ($checks as $check) {
                $this->assertSame(
                    2 * (1 + $this->countDescendants($checks, $check['id'])),
                    $check['rght'] - $check['lft'] + 1,
                    'Check ' . $check['id'] . ' does not span its descendants.',
                );
                $parent = $checks[$check['parent_id']] ?? null;
                if ($parent !== null) {
                    $this->assertTrue(
                        $parent['lft'] < $check['lft'] && $check['rght'] < $parent['rght'],
                        'Check ' . $check['id'] . ' is outside its parent.',
                    );
                }
            }
        }
    }

    /**
     * Counts the checks below a check, following parent_id.
     *
     * @param array<int, array<string, int>> $checks Checks of one package by id.
     * @param int $checkId Check id.
     * @return int
     */
    private function countDescendants(array $checks, int $checkId): int
    {
        $count = 0;
        foreach ($checks as $check) {
            if ($check['parent_id'] === $checkId) {
                $count += 1 + $this->countDescendants($checks, $check['id']);
            }
        }

        return $count;
    }
}
