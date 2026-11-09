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
$plural = fn(int $number, string $singular, string $plural): string => $number . ' ' . ($number === 1 ? $singular : $plural);

$sentences = [];
if ($totals['failed'] > 0) {
    $sentences[] = $totals['failed'] === 1
        ? '1 workstation failed its last run.'
        : $totals['failed'] . ' workstations failed their last run.';
}
if ($totals['warning'] > 0) {
    $sentences[] = $totals['warning'] === 1
        ? '1 finished with a warning.'
        : $totals['warning'] . ' finished with warnings.';
}
if ($totals['unknown'] > 0) {
    $sentences[] = $totals['unknown'] === 1
        ? '1 has no readable report.'
        : $totals['unknown'] . ' have no readable report.';
}
if ($staleCount > 0) {
    $sentences[] = $staleCount === 1
        ? '1 has not reported for more than 24 hours.'
        : $staleCount . ' have not reported for more than 24 hours.';
}
if ($sentences === []) {
    $sentences[] = $count === 1
        ? '1 workstation finished its last run without errors.'
        : 'All ' . $count . ' workstations finished their last run without errors.';
}

$barLabel = implode(', ', array_map(
    fn(ReportStatus $status): string => $status->label() . ' ' . $totals[$status->value],
    ReportSummary::STATUS_ORDER,
));
$needingAttention = $summary->needsAttention();
?>
<h2>Summary</h2><hr class="hbar" />
<div class="fleet-summary">
    <p class="fleet-sentence"><?= h(implode(' ', $sentences)) ?></p>
    <p class="fleet-latest">Latest report: <?= h($this->Wpkg->date($summary->latestRunAt())) ?>.</p>
    <svg class="fleet-bar" role="img" aria-label="<?= h($barLabel) ?>" width="100%" height="8" viewBox="0 0 100 8" preserveAspectRatio="none">
        <?php foreach ($summary->barSegments() as $segment) : ?>
            <rect class="<?= $segment['status']->cssClass() ?>" x="<?= $segment['offset'] ?>" y="0" width="<?= $segment['width'] ?>" height="8" />
        <?php endforeach; ?>
    </svg>
    <ul class="fleet-legend">
        <?php foreach (ReportSummary::STATUS_ORDER as $status) : ?>
            <li><span class="fleet-swatch <?= $status->cssClass() ?>"></span><?= h($status->label()) ?> <?= $totals[$status->value] ?></li>
        <?php endforeach; ?>
        <li><span class="fleet-swatch stale"></span>Stale <?= $staleCount ?></li>
    </ul>
</div>
<div class="fleet-columns">
    <section class="fleet-map">
        <h2>Workstations</h2><hr class="hbar" />
        <?php foreach ($groups as $group) : ?>
            <?php
            $groupCounts = $group->countByStatus();
            $breakdown = [];
            foreach ([ReportStatus::Failed, ReportStatus::Warning, ReportStatus::Unknown] as $status) {
                if (isset($groupCounts[$status->value])) {
                    $breakdown[] = $groupCounts[$status->value] . ' ' . strtolower($status->label());
                }
            }
            $hostCount = $plural(count($group->reports), 'host', 'hosts');
            ?>
            <div class="fleet-group">
                <div class="fleet-group-head">
                    <strong><?= h($group->label()) ?></strong>
                    <?php if ($group->profile() !== null) : ?>
                        <span class="report-note">profile <?= h($group->profile()) ?></span>
                    <?php endif; ?>
                    <span class="report-note"><?= h(implode(', ', [$hostCount, ...$breakdown])) ?></span>
                </div>
                <div class="fleet-cells">
                    <?php foreach ($group->reports as $report) : ?>
                        <?php
                        $status = $report->status();
                        $detail = match ($status) {
                            ReportStatus::Failed => $plural(count($report->log?->errors() ?? []), 'error', 'errors'),
                            ReportStatus::Warning => $plural(count($report->log?->warnings() ?? []), 'warning', 'warnings'),
                            default => null,
                        };
                        $isStale = $report->isStale($now);
                        $description = $report->hostname . ': ' . implode(', ', array_filter([
                            $status->label(),
                            $detail,
                            'last run ' . $this->Wpkg->date($report->ranAt()) . ($isStale ? ' (more than 24 hours ago)' : ''),
                        ]));
                        ?>
                        <a class="fleet-cell <?= $status->cssClass() ?><?= $isStale ? ' stale' : '' ?>" href="<?= h($this->Url->build('/reports/' . rawurlencode($report->hostname))) ?>" title="<?= h($description) ?>" aria-label="<?= h($description) ?>"><span aria-hidden="true"><?= $glyphs[$status->value] ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <ul class="fleet-key">
            <li><span class="fleet-swatch status-success"></span>Success</li>
            <li><span class="fleet-swatch status-failed"></span>✕ Failed</li>
            <li><span class="fleet-swatch status-warning"></span>! Warning</li>
            <li><span class="fleet-swatch status-unknown"></span>? Unknown</li>
            <li><span class="fleet-swatch stale"></span>No report for more than 24 hours</li>
        </ul>
    </section>
    <section class="fleet-attention">
        <h2>Needs attention</h2><hr class="hbar" />
        <?php if ($needingAttention === []) : ?>
            <p>Nothing to fix: every workstation succeeded in the last 24 hours.</p>
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
                            <span class="report-note"><?= h($ranAt->format($ranAt->format('Y-m-d') === $now->format('Y-m-d') ? 'h:i A' : 'Y-m-d')) ?></span>
                        </div>
                        <div class="attention-cause" title="<?= h($cause) ?>"><?= h(ReportSummary::shorten($cause)) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><?= $this->Html->link('Show all reports', '/reports') ?></p>
    </section>
</div>
