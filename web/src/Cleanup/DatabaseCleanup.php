<?php

declare(strict_types=1);

namespace App\Cleanup;

use Cake\Database\Connection;
use Cake\Database\Exception\QueryException;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Finds and deletes the rows of the link tables that point to records which no longer exist.
 *
 * The schema has no foreign keys, so a database restored from the legacy application can hold such rows.
 * They never reach the XML feeds, which only follow existing records: the cleanup does not change a feed.
 */
final class DatabaseCleanup
{
    use LocatorAwareTrait;

    /**
     * Counts the orphan rows of every table, without changing anything.
     *
     * @return array<string, int> Count by table name, in cleanup order.
     */
    public function orphanCounts(): array
    {
        $orphans = $this->orphans();
        $counts = [];
        foreach (OrphanTable::cases() as $table) {
            $counts[$table->value] = $orphans->count($table);
        }

        return $counts;
    }

    /**
     * Lists the hosts whose main profile does not exist. The cleanup keeps them.
     *
     * @return list<array{id: int, name: string, mainprofile_id: int}>
     */
    public function hostsWithoutMainProfile(): array
    {
        return $this->orphans()->hostsWithoutMainProfile();
    }

    /**
     * Deletes the orphan rows in one transaction: either every table is cleaned, or none.
     *
     * @return array<string, int> Number of rows deleted by table name, in cleanup order.
     * @throws \App\Cleanup\CleanupException When a row cannot be deleted: nothing is deleted then.
     */
    public function clean(): array
    {
        try {
            /** @var array<string, int> */
            return $this->connection()->transactional(fn(): array => $this->deleteOrphans());
        } catch (QueryException $exception) {
            throw new CleanupException(
                __('The database rejected the cleanup: {0}', $exception->getPrevious()?->getMessage() ?? ''),
                0,
                $exception,
            );
        }
    }

    /**
     * Deletes the orphan rows of every table the cleanup touches.
     *
     * @return array<string, int> Number of rows deleted by table name.
     */
    private function deleteOrphans(): array
    {
        $orphans = $this->orphans();
        $deleted = [];
        foreach (OrphanTable::cases() as $table) {
            if (!$table->isCleaned()) {
                continue;
            }
            $deleted[$table->value] = $table === OrphanTable::PackageChecks
                ? $this->deleteChecks($orphans)
                : $orphans->delete($table);
        }

        return $deleted;
    }

    /**
     * Deletes the orphan checks, with the descendants the nested set takes along.
     *
     * @param \App\Cleanup\OrphanQuery $orphans Detection queries.
     * @return int Number of rows deleted.
     */
    private function deleteChecks(OrphanQuery $orphans): int
    {
        $checks = $this->fetchTable('PackageChecks');
        $countBefore = $checks->find()->count();
        foreach ($orphans->checkIds() as $checkId) {
            $check = $checks->find()->where(['id' => $checkId])->first();
            // The check is already gone when one of its ancestors came first.
            if ($check === null) {
                continue;
            }
            if (!$checks->deleteInPackage($check)) {
                throw new CleanupException(__('Package check {0} could not be deleted.', $checkId));
            }
        }

        return $countBefore - $checks->find()->count();
    }

    /**
     * Builds the detection queries on the connection of the application tables.
     *
     * @return \App\Cleanup\OrphanQuery
     */
    private function orphans(): OrphanQuery
    {
        return new OrphanQuery($this->connection());
    }

    /**
     * Returns the connection of the application tables.
     *
     * @return \Cake\Database\Connection
     */
    private function connection(): Connection
    {
        return $this->fetchTable('PackageChecks')->getConnection();
    }
}
