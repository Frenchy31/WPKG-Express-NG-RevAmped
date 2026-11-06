<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, \App\Import\ImportReport> $reports
 * @var array<string, mixed> $settings
 */
$this->assign('title', 'Admin');
$messageGroups = ['errors' => 'Errors', 'warnings' => 'Warnings', 'notices' => 'Information'];
$yesNo = fn(mixed $flag): string => $flag ? 'Yes' : 'No';
?>
<?php foreach ($reports as $kind => $report) : ?>
    <div class="message">
        <?php if ($report->rejected) : ?>
            Uploaded <?= h(ucfirst($kind)) ?> failed to pass XSD schema validation
        <?php else : ?>
            <?= $report->saved ?> of <?= $report->total ?> uploaded <?= h(ucfirst($kind)) ?> were imported successfully
        <?php endif; ?>
    </div>
    <div class="messagesList">
        <?php foreach ($messageGroups as $property => $heading) : ?>
            <?php if ($report->{$property}) : ?>
                <?= h($heading) ?>
                <ul class="import-<?= $property ?>">
                    <?php foreach ($report->{$property} as $message) : ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<h2>Tasks</h2><hr class="hbar" />
<fieldset style="margin-left: 30px; width: 500px">
    <legend>Import</legend>
    <?= $this->Form->create(null, ['type' => 'file']) ?>
    <?= $this->Form->control('packages', ['type' => 'file', 'label' => 'Packages: ']) ?>
    <?= $this->Form->control('profiles', ['type' => 'file', 'label' => 'Profiles: ']) ?>
    <?= $this->Form->control('hosts', ['type' => 'file', 'label' => 'Hosts: ']) ?>
    <?= $this->Form->button('Import') ?>
    <?= $this->Form->end() ?>
</fieldset>
<br />
<fieldset style="margin-left: 30px; width: 500px">
    <legend>Export</legend>
    <?= $this->Html->link('Packages', '/packages.xml') ?><br />
    <?= $this->Html->link('Profiles', '/profiles.xml') ?><br />
    <?= $this->Html->link('Hosts', '/hosts.xml') ?><br />
</fieldset>
<h2>Settings</h2><hr class="hbar" />
<p style="margin-left: 30px">Settings come from environment variables and take effect when the application restarts.</p>
<fieldset class="systemsettings" style="margin-left: 30px; width: 500px">
    <legend>Web Authentication</legend>
    <div class="inputwrap"><label>Username:</label><?= h($settings['adminUser']) ?></div>
    <div class="inputwrap"><label>Force SSL:</label><?= $yesNo($settings['forceSsl']) ?></div>
</fieldset>
<br />
<fieldset class="xmlsettings" style="margin-left: 30px; width: 500px">
    <legend>XML Feed</legend>
    <div class="inputwrap"><label>Export disabled items:</label><?= $yesNo($settings['xml']['exportDisabled']) ?></div>
    <div class="inputwrap"><label>Format XML output:</label><?= $yesNo($settings['xml']['format']) ?></div>
    <div class="inputwrap"><label>Protect XML output:</label><?= $yesNo($settings['xml']['protect']) ?></div>
    <?php if ($settings['xml']['protect']) : ?>
        <div class="inputwrap"><label>Username:</label><?= h($settings['xml']['user']) ?></div>
    <?php endif; ?>
</fieldset>
