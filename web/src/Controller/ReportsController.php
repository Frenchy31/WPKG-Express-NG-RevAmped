<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\HostMatcher;
use App\Report\ReportDirectory;
use App\Report\ReportSummary;
use Cake\Http\Exception\NotFoundException;
use DateTimeImmutable;

/**
 * Shows the last run of each workstation, read from the reports directory of the share.
 */
class ReportsController extends AppController
{
    /**
     * Lists the workstations grouped by matching host entry.
     *
     * @return void
     */
    public function index(): void
    {
        $directory = ReportDirectory::fromConfiguration();
        $reports = $directory->all();
        $now = new DateTimeImmutable();

        $summary = new ReportSummary($reports, $now);

        $this->set([
            'configured' => $directory->isConfigured(),
            'readable' => $directory->isReadable(),
            'groups' => (new HostMatcher())->group($reports),
            'totals' => $summary->totals(),
            'staleCount' => $summary->staleCount(),
            'now' => $now,
        ]);
    }

    /**
     * Shows the full log of a workstation.
     *
     * @param string $hostname Host name, as in the file names.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException When the workstation has no report.
     */
    public function view(string $hostname): void
    {
        $report = ReportDirectory::fromConfiguration()->find($hostname);
        if ($report === null) {
            throw new NotFoundException('No report found for this host.');
        }

        $this->set([
            'report' => $report,
            'now' => new DateTimeImmutable(),
        ]);
    }
}
