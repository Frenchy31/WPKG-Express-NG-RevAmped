<?php
/**
 * Facts about the last run of a workstation.
 *
 * @var \App\View\AppView $this
 * @var \App\Report\HostReport $report
 */
$facts = [
    'Last run' => $this->Wpkg->date($report->ranAt()),
    'WPKG version' => $report->log?->wpkgVersion(),
    'Host name' => $report->properties['hostname'] ?? null,
    'Operating system' => $report->properties['os'] ?? null,
    'Architecture' => $report->properties['architecture'] ?? null,
    'IP addresses' => implode(', ', $report->ipAddresses()),
    'Domain' => $report->properties['domainname'] ?? null,
    'Profiles' => implode(', ', $report->log?->profiles() ?? []),
    'Installed packages' => $report->installedPackages === null ? null : (string)$report->installedPackages,
    'Log file' => $report->log === null ? 'Missing' : null,
    'XML file' => $report->hasXml ? $report->xmlError : 'Missing',
];
$lists = [
    'Succeeded' => ['report-succeeded', $report->log?->succeeded() ?? []],
    'Errors' => ['report-errors', $report->log?->errors() ?? []],
    'Warnings' => ['report-warnings', $report->log?->warnings() ?? []],
];
?>
<dl class="report-details">
    <?php foreach ($facts as $label => $value) : ?>
        <?php if ($value !== null && $value !== '') : ?>
            <dt><?= h($label) ?></dt>
            <dd><?= h($value) ?></dd>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ($lists as $label => [$class, $messages]) : ?>
        <?php if ($messages !== []) : ?>
            <dt><?= h($label) ?> (<?= count($messages) ?>)</dt>
            <dd>
                <ul class="<?= $class ?>">
                    <?php foreach ($messages as $message) : ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </dd>
        <?php endif; ?>
    <?php endforeach; ?>
</dl>
