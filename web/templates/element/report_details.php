<?php
/**
 * Facts about the last run of a workstation.
 *
 * @var \App\View\AppView $this
 * @var \App\Report\HostReport $report
 */
$facts = [
    __('Last run') => $this->Wpkg->date($report->ranAt()),
    __('WPKG version') => $report->log?->wpkgVersion(),
    __('Host name') => $report->properties['hostname'] ?? null,
    __('Operating system') => $report->properties['os'] ?? null,
    __('Architecture') => $report->properties['architecture'] ?? null,
    __('IP addresses') => implode(', ', $report->ipAddresses()),
    __('Domain') => $report->properties['domainname'] ?? null,
    __('Profiles') => implode(', ', $report->log?->profiles() ?? []),
    __('Installed packages') => $report->installedPackages === null ? null : (string)$report->installedPackages,
    __('Log file') => $report->log === null ? __('Missing') : $report->logError,
    __('XML file') => $report->hasXml ? $report->xmlError : __('Missing'),
];
$lists = [
    __('Succeeded') => ['report-succeeded', $report->log?->succeeded() ?? []],
    __('Errors') => ['report-errors', $report->log?->errors() ?? []],
    __('Warnings') => ['report-warnings', $report->log?->warnings() ?? []],
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
