<?php
/**
 * @var \App\View\AppView $this
 * @var bool $configured
 * @var bool $readable
 * @var list<\App\Report\ReportGroup> $groups
 * @var array<string, int> $totals
 * @var int $staleCount
 * @var \DateTimeImmutable $now
 */

use App\Report\ReportStatus;

$this->assign('title', 'Reports');
?>
<h2>Reports</h2><hr class="hbar" />
<?php if (!$configured) : ?>
    <div class="message">Reports directory not configured: set WPKG_REPORTS_PATH</div>
<?php elseif (!$readable) : ?>
    <div class="message">Reports directory cannot be read</div>
<?php elseif ($groups === []) : ?>
    <div class="message">No reports found</div>
<?php else : ?>
    <p class="report-totals">
        <?php foreach ([ReportStatus::Failed, ReportStatus::Warning, ReportStatus::Unknown, ReportStatus::Success] as $status) : ?>
            <span class="report-badge <?= $status->cssClass() ?>"><?= h($status->label()) ?>: <?= $totals[$status->value] ?></span>
        <?php endforeach; ?>
        <span class="report-badge stale">Stale: <?= $staleCount ?></span>
    </p>
    <?php foreach ($groups as $group) : ?>
        <?php $groupStatus = $group->status(); ?>
        <details class="report-group <?= $groupStatus->cssClass() ?>"<?= $groupStatus === ReportStatus::Success ? '' : ' open' ?>>
            <summary>
                <span class="report-badge <?= $groupStatus->cssClass() ?>"><?= h($groupStatus->label()) ?></span>
                <strong><?= h($group->label()) ?></strong>
                <?php if ($group->profile() !== null) : ?>
                    <span class="report-note">profile <?= h($group->profile()) ?></span>
                <?php endif; ?>
                <span class="report-note"><?= count($group->reports) ?> host(s)</span>
            </summary>
            <?php foreach ($group->reports as $report) : ?>
                <details class="report <?= $report->status()->cssClass() ?>">
                    <summary>
                        <?= $this->element('report_badges', ['report' => $report, 'now' => $now]) ?>
                        <?= $this->Html->link($report->hostname, '/reports/' . rawurlencode($report->hostname), ['title' => 'Show the full log']) ?>
                        <span class="report-note"><?= $this->Wpkg->date($report->ranAt()) ?></span>
                        <?php if ($report->log !== null) : ?>
                            <span class="report-note">
                                <?= count($report->log->errors()) ?> error(s), <?= count($report->log->warnings()) ?> warning(s)
                            </span>
                        <?php endif; ?>
                    </summary>
                    <?= $this->element('report_details', ['report' => $report]) ?>
                </details>
            <?php endforeach; ?>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
