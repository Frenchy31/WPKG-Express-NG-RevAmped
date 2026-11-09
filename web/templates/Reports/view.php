<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Report\HostReport $report
 * @var \DateTimeImmutable $now
 */
$this->assign('title', __('Report {0}', $report->hostname));
?>
<h2><?= __('Report') ?> - <?= h($report->hostname) ?> <?= $this->element('report_badges', ['report' => $report, 'now' => $now]) ?></h2><hr class="hbar" />
<p><?= $this->Html->link(__('Back to reports'), '/reports') ?></p>
<?= $this->element('report_details', ['report' => $report]) ?>

<h3><?= __('Last log') ?></h3>
<?php if ($report->log === null) : ?>
    <div class="message"><?= __('No log file for this host') ?></div>
<?php else : ?>
    <ol class="report-log">
        <?php foreach ($report->log->entries() as $entry) : ?>
            <li class="<?= $entry->cssClass() ?>"><div class="report-entry">
                <?php if ($entry->time !== null) : ?>
                    <span class="report-time"><?= h($entry->time->format('Y-m-d H:i:s')) ?></span>
                <?php endif; ?>
                <?php if ($entry->level !== null) : ?>
                    <span class="report-level"><?= h($entry->level) ?></span>
                <?php endif; ?>
                <span class="report-message"><?= h($entry->message) ?></span>
            </div></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
