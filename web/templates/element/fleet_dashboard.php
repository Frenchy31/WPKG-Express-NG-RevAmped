<?php
/**
 * Fleet state: status sentence, status bar, room map and workstations needing attention.
 *
 * @var \App\View\AppView $this
 * @var list<\App\Report\ReportGroup> $groups
 * @var \App\Report\ReportSummary $summary
 * @var \DateTimeImmutable $now
 */

use App\Report\ReportStatus;
use App\Report\ReportSummary;

$totals = $summary->totals();
$staleCount = $summary->staleCount();
$count = $summary->count();
$glyphs = [
    ReportStatus::Failed->value => '✕',
    ReportStatus::Warning->value => '!',
    ReportStatus::Unknown->value => '?',
    ReportStatus::Success->value => '',
];
$sentences = [];
if ($totals['failed'] > 0) {
    $sentences[] = __n(
        '{0} workstation failed its last run.',
        '{0} workstations failed their last run.',
        $totals['failed'],
        (string)$totals['failed'],
    );
}
if ($totals['warning'] > 0) {
    $sentences[] = __n(
        '{0} finished with a warning.',
        '{0} finished with warnings.',
        $totals['warning'],
        (string)$totals['warning'],
    );
}
if ($totals['unknown'] > 0) {
    $sentences[] = __n(
        '{0} has no readable report.',
        '{0} have no readable report.',
        $totals['unknown'],
        (string)$totals['unknown'],
    );
}
if ($staleCount > 0) {
    $sentences[] = __n(
        '{0} has not reported for more than 24 hours.',
        '{0} have not reported for more than 24 hours.',
        $staleCount,
        (string)$staleCount,
    );
}
if ($sentences === []) {
    $sentences[] = __n(
        '{0} workstation finished its last run without errors.',
        'All {0} workstations finished their last run without errors.',
        $count,
        (string)$count,
    );
}

$barLabel = implode(', ', array_map(
    fn(ReportStatus $status): string => $status->label() . ' ' . $totals[$status->value],
    ReportSummary::STATUS_ORDER,
));
$needingAttention = $summary->needsAttention();
?>
<h2><?= __('Summary') ?></h2><hr class="hbar" />
<div class="fleet-summary">
    <p class="fleet-sentence"><?= h(implode(' ', $sentences)) ?></p>
    <p class="fleet-latest"><?= h(__('Latest report: {0}.', $this->Wpkg->date($summary->latestRunAt()))) ?></p>
    <svg class="fleet-bar" role="img" aria-label="<?= h($barLabel) ?>" width="100%" height="8" viewBox="0 0 100 8" preserveAspectRatio="none">
        <?php foreach ($summary->barSegments() as $segment) : ?>
            <rect class="<?= $segment['status']->cssClass() ?>" x="<?= $segment['offset'] ?>" y="0" width="<?= $segment['width'] ?>" height="8" />
        <?php endforeach; ?>
    </svg>
    <ul class="fleet-legend">
        <?php foreach (ReportSummary::STATUS_ORDER as $status) : ?>
            <li><span class="fleet-swatch <?= $status->cssClass() ?>"></span><?= h($status->label()) ?> <?= $totals[$status->value] ?></li>
        <?php endforeach; ?>
        <li><span class="fleet-swatch stale"></span><?= __('Stale') ?> <?= $staleCount ?></li>
    </ul>
</div>
<div class="fleet-columns">
    <section class="fleet-map">
        <h2><?= __('Workstations') ?></h2><hr class="hbar" />
        <?php foreach ($groups as $group) : ?>
            <?php
            $groupCounts = $group->countByStatus();
            $breakdown = [];
            foreach ([ReportStatus::Failed, ReportStatus::Warning, ReportStatus::Unknown] as $status) {
                if (isset($groupCounts[$status->value])) {
                    $breakdown[] = match ($status) {
                        ReportStatus::Failed => __('{0} failed', (string)$groupCounts[$status->value]),
                        ReportStatus::Warning => __x('breakdown', '{0} warning', (string)$groupCounts[$status->value]),
                        default => __('{0} unknown', (string)$groupCounts[$status->value]),
                    };
                }
            }
            $hostCount = __n('{0} host', '{0} hosts', count($group->reports), (string)count($group->reports));
            ?>
            <div class="fleet-group">
                <div class="fleet-group-head">
                    <strong><?= h($group->label()) ?></strong>
                    <?php if ($group->profile() !== null) : ?>
                        <span class="report-note"><?= h(__('profile {0}', $group->profile())) ?></span>
                    <?php endif; ?>
                    <span class="report-note"><?= h(implode(', ', [$hostCount, ...$breakdown])) ?></span>
                </div>
                <div class="fleet-cells">
                    <?php foreach ($group->reports as $report) : ?>
                        <?php
                        $status = $report->status();
                        $detail = match ($status) {
                            ReportStatus::Failed => __n(
                                '{0} error',
                                '{0} errors',
                                count($report->log?->errors() ?? []),
                                (string)count($report->log?->errors() ?? []),
                            ),
                            ReportStatus::Warning => __n(
                                '{0} warning',
                                '{0} warnings',
                                count($report->log?->warnings() ?? []),
                                (string)count($report->log?->warnings() ?? []),
                            ),
                            default => null,
                        };
                        $isStale = $report->isStale($now);
                        $lastRun = $isStale
                            ? __('last run {0} (more than 24 hours ago)', $this->Wpkg->date($report->ranAt()))
                            : __('last run {0}', $this->Wpkg->date($report->ranAt()));
                        $description = __('{0}: {1}', $report->hostname, implode(', ', array_filter([
                            $status->label(),
                            $detail,
                            $lastRun,
                        ])));
                        ?>
                        <a class="fleet-cell <?= $status->cssClass() ?><?= $isStale ? ' stale' : '' ?>" href="<?= h($this->Url->build('/reports/' . rawurlencode($report->hostname))) ?>" title="<?= h($description) ?>" aria-label="<?= h($description) ?>"><span aria-hidden="true"><?= $glyphs[$status->value] ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <ul class="fleet-key">
            <li><span class="fleet-swatch status-success"></span><?= h(ReportStatus::Success->label()) ?></li>
            <li><span class="fleet-swatch status-failed"></span>✕ <?= h(ReportStatus::Failed->label()) ?></li>
            <li><span class="fleet-swatch status-warning"></span>! <?= h(ReportStatus::Warning->label()) ?></li>
            <li><span class="fleet-swatch status-unknown"></span>? <?= h(ReportStatus::Unknown->label()) ?></li>
            <li><span class="fleet-swatch stale"></span><?= __('No report for more than 24 hours') ?></li>
        </ul>
    </section>
    <section class="fleet-attention">
        <h2><?= __('Needs attention') ?></h2><hr class="hbar" />
        <?php if ($needingAttention === []) : ?>
            <p><?= __('Nothing to fix: every workstation succeeded in the last 24 hours.') ?></p>
        <?php else : ?>
            <ul class="attention-list">
                <?php foreach ($needingAttention as $report) : ?>
                    <?php
                    $cause = $summary->cause($report);
                    $ranAt = $report->ranAt();
                    ?>
                    <li class="attention-row">
                        <div class="attention-head">
                            <a href="<?= h($this->Url->build('/reports/' . rawurlencode($report->hostname))) ?>"><?= h($report->hostname) ?></a>
                            <?= $this->element('report_badges', ['report' => $report, 'now' => $now]) ?>
                            <span class="report-note"><?= h($ranAt->format($ranAt->format('Y-m-d') === $now->format('Y-m-d') ? $this->language()->timeFormat() : 'Y-m-d')) ?></span>
                        </div>
                        <div class="attention-cause" title="<?= h($cause) ?>"><?= h(ReportSummary::shorten($cause)) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><?= $this->Html->link(__('Show all reports'), '/reports') ?></p>
    </section>
</div>
