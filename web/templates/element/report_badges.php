<?php
/**
 * Status and staleness of a workstation.
 *
 * @var \App\View\AppView $this
 * @var \App\Report\HostReport $report
 * @var \DateTimeImmutable $now
 */
$status = $report->status();
?>
<span class="report-badge <?= $status->cssClass() ?>"><?= h($status->label()) ?></span>
<?php if ($report->isStale($now)) : ?>
    <span class="report-badge stale" title="No run in the last 24 hours">Stale</span>
<?php endif; ?>
