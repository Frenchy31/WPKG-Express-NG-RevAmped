<?php
/**
 * @var \App\View\AppView $this
 * @var bool $configured
 * @var list<\App\Report\ReportGroup> $groups
 * @var array<string, int> $totals
 * @var int $staleCount
 * @var \App\Report\IndexState|null $indexState
 * @var bool $indexOverdue
 * @var \DateTimeImmutable $now
 */

use App\Report\ReportStatus;

$this->assign('title', __('Reports'));
?>
<h2><?= __('Reports') ?></h2><hr class="hbar" />
<?php if ($configured) : ?>
    <?= $this->element('report_index_status', ['indexState' => $indexState, 'indexOverdue' => $indexOverdue]) ?>
<?php endif; ?>
<?php if (!$configured) : ?>
    <div class="message"><?= __('Reports directory not configured: set WPKG_REPORTS_PATH') ?></div>
<?php elseif ($groups === []) : ?>
    <div class="message"><?= __('No reports found') ?></div>
<?php else : ?>
    <p class="report-totals">
        <?php foreach ([ReportStatus::Failed, ReportStatus::Warning, ReportStatus::Unknown, ReportStatus::Success] as $status) : ?>
            <span class="report-badge <?= $status->cssClass() ?>"><?= h(__('{0}: {1}', $status->label(), (string)$totals[$status->value])) ?></span>
        <?php endforeach; ?>
        <span class="report-badge stale"><?= h(__('Stale: {0}', (string)$staleCount)) ?></span>
    </p>
    <?php foreach ($groups as $group) : ?>
        <?php $groupStatus = $group->status(); ?>
        <details class="report-group <?= $groupStatus->cssClass() ?>"<?= $groupStatus === ReportStatus::Success ? '' : ' open' ?>>
            <summary>
                <span class="report-badge <?= $groupStatus->cssClass() ?>"><?= h($groupStatus->label()) ?></span>
                <strong><?= h($group->label()) ?></strong>
                <?php if ($group->profile() !== null) : ?>
                    <span class="report-note"><?= h(__('profile {0}', $group->profile())) ?></span>
                <?php endif; ?>
                <span class="report-note"><?= h(__('{0} host(s)', (string)count($group->reports))) ?></span>
            </summary>
            <?php foreach ($group->reports as $report) : ?>
                <details class="report <?= $report->status()->cssClass() ?>">
                    <summary>
                        <?= $this->element('report_badges', ['report' => $report, 'now' => $now]) ?>
                        <?= $this->Html->link($report->hostname, '/reports/' . rawurlencode($report->hostname), ['title' => __('Show the full log')]) ?>
                        <span class="report-note"><?= $this->Wpkg->date($report->ranAt()) ?></span>
                        <?php if ($report->log !== null) : ?>
                            <span class="report-note">
                                <?= h(__('{0} error(s), {1} warning(s)', (string)count($report->log->errors()), (string)count($report->log->warnings()))) ?>
                            </span>
                        <?php endif; ?>
                    </summary>
                    <?= $this->element('report_details', ['report' => $report]) ?>
                </details>
            <?php endforeach; ?>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
