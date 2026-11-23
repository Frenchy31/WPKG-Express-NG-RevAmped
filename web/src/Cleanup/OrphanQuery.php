<?php

declare(strict_types=1);

namespace App\Cleanup;

use App\Model\Enum\VariableType;
use Cake\Database\Connection;

/**
 * SQL detecting the rows whose parent record no longer exists.
 *
 * Each definition is a `FROM ... WHERE` fragment selecting the orphans as `t`. The count and the delete
 * share it, so they always agree. The cleanup never deletes a parent record, which is why a fragment never
 * depends on another table being cleaned first. The only chains are exit codes, which also follow the package
 * of their action, and checks, which also follow their parent.
 */
final class OrphanQuery
{
    /**
     * Orphan checks: package missing, or parent missing. A parent of another package does not count, because
     * the feed only walks the checks of one package. A parent_id of 0 is a root, as in the feed.
     */
    private const string CHECKS = <<<'SQL'
        FROM `package_checks` t
        LEFT JOIN `packages` p ON p.id = t.package_id
        LEFT JOIN `package_checks` parent ON parent.id = t.parent_id AND parent.package_id = t.package_id
        WHERE p.id IS NULL OR (t.parent_id IS NOT NULL AND t.parent_id <> 0 AND parent.id IS NULL)
        SQL;

    private const string HOSTS = <<<'SQL'
        FROM `hosts` t
        LEFT JOIN `profiles` p ON p.id = t.mainprofile_id
        WHERE p.id IS NULL
        SQL;

    /**
     * @param \Cake\Database\Connection $connection Connection of the application database.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Counts the orphan rows of a table.
     *
     * @param \App\Cleanup\OrphanTable $table Table.
     * @return int
     */
    public function count(OrphanTable $table): int
    {
        return match ($table) {
            OrphanTable::PackageChecks => count($this->checkIds()),
            OrphanTable::Hosts => count($this->hostsWithoutMainProfile()),
            default => (int)$this->connection->execute('SELECT COUNT(*) ' . $this->source($table))->fetchColumn(0),
        };
    }

    /**
     * Deletes the orphan rows of a table with one statement. Checks go through the nested set and hosts are
     * never deleted: they have no definition here.
     *
     * @param \App\Cleanup\OrphanTable $table Table.
     * @return int Number of rows deleted.
     */
    public function delete(OrphanTable $table): int
    {
        return $this->connection->execute('DELETE t ' . $this->source($table))->rowCount();
    }

    /**
     * Lists the checks to delete: the orphans and every check below them, which the delete would take along.
     *
     * @return list<int> Check ids in ascending order.
     */
    public function checkIds(): array
    {
        $orphanIds = $this->ids('SELECT t.id ' . self::CHECKS);
        $known = array_fill_keys($orphanIds, true);
        $frontier = $orphanIds;
        while ($frontier !== []) {
            $frontier = array_values(array_filter(
                $this->childCheckIds($frontier),
                fn(int $checkId): bool => !isset($known[$checkId]),
            ));
            $known += array_fill_keys($frontier, true);
        }
        $checkIds = array_keys($known);
        sort($checkIds);

        return $checkIds;
    }

    /**
     * Lists the hosts whose main profile does not exist.
     *
     * @return list<array{id: int, name: string, mainprofile_id: int}>
     */
    public function hostsWithoutMainProfile(): array
    {
        $rows = $this->connection
            ->execute('SELECT t.id, t.name, t.mainprofile_id ' . self::HOSTS . ' ORDER BY t.position, t.id')
            ->fetchAll('assoc');

        return array_map(
            fn(array $row): array => [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'mainprofile_id' => (int)$row['mainprofile_id'],
            ],
            $rows,
        );
    }

    /**
     * Returns the `FROM ... WHERE` fragment selecting the orphans of a table deleted with a single statement.
     *
     * @param \App\Cleanup\OrphanTable $table Table.
     * @return string
     */
    private function source(OrphanTable $table): string
    {
        return match ($table) {
            OrphanTable::ExitCodes => <<<'SQL'
                FROM `exit_codes` t
                LEFT JOIN `package_actions` a ON a.id = t.package_action_id
                LEFT JOIN `packages` p ON p.id = a.package_id
                WHERE a.id IS NULL OR p.id IS NULL
                SQL,
            OrphanTable::PackageActions => <<<'SQL'
                FROM `package_actions` t
                LEFT JOIN `packages` p ON p.id = t.package_id
                WHERE p.id IS NULL
                SQL,
            OrphanTable::Variables => sprintf(
                <<<'SQL'
                FROM `variables` t
                LEFT JOIN `packages` pk ON t.ref_type = %1$d AND pk.id = t.ref_id
                LEFT JOIN `profiles` pr ON t.ref_type = %2$d AND pr.id = t.ref_id
                LEFT JOIN `hosts` h ON t.ref_type = %3$d AND h.id = t.ref_id
                WHERE (t.ref_type = %1$d AND pk.id IS NULL)
                    OR (t.ref_type = %2$d AND pr.id IS NULL)
                    OR (t.ref_type = %3$d AND h.id IS NULL)
                SQL,
                VariableType::Package->value,
                VariableType::Profile->value,
                VariableType::Host->value,
            ),
            OrphanTable::HostsProfiles => <<<'SQL'
                FROM `hosts_profiles` t
                LEFT JOIN `hosts` h ON h.id = t.host_id
                LEFT JOIN `profiles` p ON p.id = t.profile_id
                WHERE h.id IS NULL OR p.id IS NULL
                SQL,
            OrphanTable::PackagesProfiles => <<<'SQL'
                FROM `packages_profiles` t
                LEFT JOIN `packages` pk ON pk.id = t.package_id
                LEFT JOIN `profiles` pr ON pr.id = t.profile_id
                WHERE pk.id IS NULL OR pr.id IS NULL
                SQL,
            OrphanTable::PackagesPackages => <<<'SQL'
                FROM `packages_packages` t
                LEFT JOIN `packages` pk ON pk.id = t.package_id
                LEFT JOIN `packages` dep ON dep.id = t.dependency_id
                WHERE pk.id IS NULL OR dep.id IS NULL
                SQL,
            OrphanTable::ProfilesProfiles => <<<'SQL'
                FROM `profiles_profiles` t
                LEFT JOIN `profiles` pr ON pr.id = t.profile_id
                LEFT JOIN `profiles` dep ON dep.id = t.dependency_id
                WHERE pr.id IS NULL OR dep.id IS NULL
                SQL,
        };
    }

    /**
     * Lists the checks whose parent is one of the given checks.
     *
     * @param list<int> $parentIds Parent check ids.
     * @return list<int>
     */
    private function childCheckIds(array $parentIds): array
    {
        $placeholders = implode(', ', array_fill(0, count($parentIds), '?'));

        return $this->ids('SELECT id FROM `package_checks` WHERE parent_id IN (' . $placeholders . ')', $parentIds);
    }

    /**
     * Runs a query selecting ids.
     *
     * @param string $sql Query returning the ids as first column.
     * @param list<int> $params Positional parameters.
     * @return list<int>
     */
    private function ids(string $sql, array $params = []): array
    {
        return array_map(
            fn(array $row): int => (int)$row[0],
            $this->connection->execute($sql, $params)->fetchAll('num'),
        );
    }
}
