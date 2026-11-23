<?php

declare(strict_types=1);

namespace App\Test\Support;

use App\Backup\BackupRepository;
use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

/**
 * Captures what an import or a reset must leave unchanged or restore: the table rows and the three feeds.
 */
trait DatabaseSnapshotTrait
{
    /**
     * Reads every row of the business tables, as stored.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function snapshot(): array
    {
        $snapshot = [];
        foreach (BackupRepository::TABLES as $table) {
            $rows = $this->testConnection()->execute('SELECT * FROM `' . $table . '` ORDER BY `id`')->fetchAll('assoc');
            $snapshot[$table] = $rows;
        }

        return $snapshot;
    }

    /**
     * Counts the rows of the business tables.
     *
     * @return int
     */
    protected function rowCount(): int
    {
        return array_sum(array_map('count', $this->snapshot()));
    }

    /**
     * Renders hosts.xml, profiles.xml and packages.xml in the formats clients can ask for.
     *
     * @return array<string, string>
     */
    protected function feedDocuments(): array
    {
        $documents = [];
        foreach (['indented' => [true, false], 'compact with disabled items' => [false, true]] as $name => $options) {
            [$format, $exportDisabled] = $options;
            $documents['hosts, ' . $name] = (new HostsFeed($format, $exportDisabled))->document();
            $documents['profiles, ' . $name] = (new ProfilesFeed($format, $exportDisabled))->document();
            $documents['packages, ' . $name] = (new PackagesFeed($format, $exportDisabled))->document();
        }

        return $documents;
    }

    /**
     * Returns the connection of the test database.
     *
     * @return \Cake\Database\Connection
     */
    protected function testConnection(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        return $connection;
    }
}
