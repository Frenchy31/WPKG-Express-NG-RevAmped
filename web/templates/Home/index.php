<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, array<string, mixed>> $statistics
 * @var bool $configured
 * @var bool $readable
 * @var list<\App\Report\ReportGroup> $groups
 * @var \App\Report\ReportSummary $summary
 * @var \DateTimeImmutable $now
 */
$this->assign('title', __('Summary'));
$tableLabels = ['Packages' => __('Packages'), 'Profiles' => __('Profiles'), 'Hosts' => __('Hosts')];
?>
<?php if (!$configured) : ?>
    <div class="message"><?= __('Workstation reports are off. Set WPKG_REPORTS_PATH to show the last run of each host.') ?></div>
<?php elseif (!$readable) : ?>
    <div class="message"><?= __('Reports directory cannot be read') ?></div>
<?php elseif ($groups === []) : ?>
    <div class="message"><?= __('No reports found') ?></div>
<?php else : ?>
    <?= $this->element('fleet_dashboard', ['groups' => $groups, 'summary' => $summary, 'now' => $now]) ?>
<?php endif; ?>
<div id="stats-container">
    <?php foreach ($statistics as $tableName => $statistic) : ?>
        <div class="statsblock">
            <h2><?= h($tableLabels[$tableName] ?? $tableName) ?></h2><hr class="hbar" />
            <div class="inputwrap"><label><?= __('# Enabled:') ?></label><?= $statistic['enabled'] ?></div>
            <div class="inputwrap"><label><?= __('# Disabled:') ?></label><?= $statistic['disabled'] ?></div>
            <div class="inputwrap"><label><?= __('# Total:') ?></label><?= $statistic['total'] ?></div>
            <div class="inputwrap"><label><?= __('Last Modified:') ?></label><ol class="reg">
                <?php foreach ($statistic['recent'] as $record) : ?>
                    <li>
                        <?= $this->Html->link($record->get($statistic['displayField']), ['controller' => $tableName, 'action' => 'view', $record->id]) ?>
                        (<div class="date"><?= $this->Wpkg->date($record->modified) ?></div>)
                    </li>
                <?php endforeach; ?>
            </ol></div>
        </div>
    <?php endforeach; ?>
</div>
