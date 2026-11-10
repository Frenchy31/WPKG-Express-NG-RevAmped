<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Report\HostReport;
use Cake\ORM\Table;

/**
 * One summary row per workstation, written by the reports indexer and read by the Reports pages.
 */
class HostReportsTable extends Table
{
    /**
     * Declares the table.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('host_reports');
        $this->setPrimaryKey('hostname');
        $this->setDisplayField('hostname');
    }

    /**
     * Returns the report of every indexed workstation.
     *
     * @return list<\App\Report\HostReport>
     */
    public function allReports(): array
    {
        $rows = $this->find()->disableHydration()->all();

        return array_values(array_map(fn(array $row): HostReport => HostReport::fromRow($row), $rows->toArray()));
    }

    /**
     * Returns the file dates and sizes recorded for a workstation.
     *
     * @param string $hostname Host name.
     * @return array<string, int|null>|null Null when the workstation is not indexed.
     */
    public function recordedFiles(string $hostname): ?array
    {
        /** @var array<string, int|null>|null */
        return $this->find()
            ->select(['log_mtime', 'log_size', 'xml_mtime', 'xml_size'])
            ->where(['hostname' => $hostname])
            ->disableHydration()
            ->first();
    }

    /**
     * Creates or updates the row of a workstation.
     *
     * @param string $hostname Host name.
     * @param array<string, mixed> $values Columns to write.
     * @param bool $exists Whether the row exists.
     * @return void
     */
    public function store(string $hostname, array $values, bool $exists): void
    {
        if ($exists) {
            $this->updateAll($values, ['hostname' => $hostname]);

            return;
        }

        $this->insertQuery()->insert(array_keys($values + ['hostname' => $hostname]))
            ->values($values + ['hostname' => $hostname])
            ->execute();
    }

    /**
     * Lists indexed host names in order, one page at a time.
     *
     * @param string $after Host name to start after, empty for the first page.
     * @param int $limit Page size.
     * @return list<string>
     */
    public function hostnamesAfter(string $after, int $limit): array
    {
        $query = $this->find()->select(['hostname'])->orderBy(['hostname' => 'ASC'])->limit($limit)->disableHydration();
        if ($after !== '') {
            $query->where(['hostname >' => $after]);
        }

        return array_values(array_column($query->all()->toArray(), 'hostname'));
    }

    /**
     * Deletes the row of a workstation.
     *
     * @param string $hostname Host name.
     * @return void
     */
    public function forget(string $hostname): void
    {
        $this->deleteAll(['hostname' => $hostname]);
    }
}
