<?php

declare(strict_types=1);

namespace App\Test\Support;

use App\Report\IndexState;
use App\Report\ReportDirectory;
use App\Report\ReportIndexer;
use Cake\Datasource\ConnectionManager;

/**
 * Copies tests/Fixture/reports to a temporary directory the tests can change, and indexes it.
 */
trait ReportFixturesTrait
{
    private string $reportsPath = '';

    /**
     * Creates the temporary reports directory and empties the index.
     *
     * @return string Directory.
     */
    protected function copyReportFixtures(): string
    {
        $this->emptyReportIndex();
        $this->reportsPath = sys_get_temp_dir() . DS . 'wpkg-reports-' . bin2hex(random_bytes(6));
        mkdir($this->reportsPath);
        foreach ((array)glob(TESTS . 'Fixture' . DS . 'reports' . DS . '*') as $source) {
            copy((string)$source, $this->reportsPath . DS . basename((string)$source));
        }

        return $this->reportsPath;
    }

    /**
     * Runs one indexing pass on the temporary reports directory.
     *
     * @return \App\Report\IndexState
     */
    protected function indexReportFixtures(): IndexState
    {
        return (new ReportIndexer(new ReportDirectory($this->reportsPath)))->run();
    }

    /**
     * Deletes the temporary reports directory and empties the index.
     *
     * @return void
     */
    protected function removeReportFixtures(): void
    {
        $this->emptyReportIndex();
        if ($this->reportsPath === '') {
            return;
        }
        foreach ((array)glob($this->reportsPath . DS . '*') as $file) {
            unlink((string)$file);
        }
        rmdir($this->reportsPath);
        $this->reportsPath = '';
    }

    /**
     * Empties the two tables of the reports index.
     *
     * @return void
     */
    private function emptyReportIndex(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('TRUNCATE TABLE `host_reports`');
        $connection->execute('TRUNCATE TABLE `report_index_state`');
    }
}
