<?php

declare(strict_types=1);

namespace App\Test\Support;

/**
 * Copies tests/Fixture/reports to a temporary directory the tests can change.
 */
trait ReportFixturesTrait
{
    private string $reportsPath = '';

    /**
     * Creates the temporary reports directory.
     *
     * @return string Directory.
     */
    protected function copyReportFixtures(): string
    {
        $this->reportsPath = sys_get_temp_dir() . DS . 'wpkg-reports-' . bin2hex(random_bytes(6));
        mkdir($this->reportsPath);
        foreach ((array)glob(TESTS . 'Fixture' . DS . 'reports' . DS . '*') as $source) {
            copy((string)$source, $this->reportsPath . DS . basename((string)$source));
        }

        return $this->reportsPath;
    }

    /**
     * Deletes the temporary reports directory.
     *
     * @return void
     */
    protected function removeReportFixtures(): void
    {
        if ($this->reportsPath === '') {
            return;
        }
        foreach ((array)glob($this->reportsPath . DS . '*') as $file) {
            unlink((string)$file);
        }
        rmdir($this->reportsPath);
        $this->reportsPath = '';
    }
}
