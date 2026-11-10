<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\HostMatcher;
use App\Report\IndexState;
use App\Report\ReportDirectory;
use App\Report\ReportSummary;
use App\Report\RunLog;
use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use DateTimeImmutable;

/**
 * Shows the last run of each workstation: the list reads the index table, the detail reads the log file.
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
        $isConfigured = ReportDirectory::fromConfiguration()->isConfigured();
        $reports = $isConfigured ? $this->fetchTable('HostReports')->allReports() : [];
        $indexState = $this->fetchTable('ReportIndexState')->latest();
        $intervalSeconds = (int)Configure::read('Wpkg.reportsIndexInterval');
        $now = new DateTimeImmutable();

        $summary = new ReportSummary($reports, $now);

        $this->set([
            'configured' => $isConfigured,
            'groups' => (new HostMatcher())->group($reports),
            'totals' => $summary->totals(),
            'staleCount' => $summary->staleCount(),
            'indexState' => $indexState,
            'indexOverdue' => IndexState::isOverdue($indexState, $now, $intervalSeconds),
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
            throw new NotFoundException(__('No report found for this host.'));
        }

        $this->set([
            'report' => $report,
            'runLog' => $report->log instanceof RunLog ? $report->log : null,
            'now' => new DateTimeImmutable(),
        ]);
    }
}
