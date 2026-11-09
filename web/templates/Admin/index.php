<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, \App\Import\ImportReport> $reports
 * @var array<string, mixed> $settings
 */
$this->assign('title', __('Admin'));
$messageGroups = ['errors' => __('Errors'), 'warnings' => __('Warnings'), 'notices' => __('Information')];
$kindLabels = ['packages' => __('Packages'), 'profiles' => __('Profiles'), 'hosts' => __('Hosts')];
$yesNo = fn(mixed $flag): string => $flag ? __('Yes') : __('No');
?>
<?php foreach ($reports as $kind => $report) : ?>
    <div class="message">
        <?php if ($report->rejected) : ?>
            <?= h(__('Uploaded {0} failed to pass XSD schema validation', $kindLabels[$kind])) ?>
        <?php else : ?>
            <?= h(__('{0} of {1} uploaded {2} were imported successfully', (string)$report->saved, (string)$report->total, $kindLabels[$kind])) ?>
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

<h2><?= __('Tasks') ?></h2><hr class="hbar" />
<fieldset>
    <legend><?= __('Import') ?></legend>
    <?= $this->Form->create(null, ['type' => 'file']) ?>
    <?= $this->Form->control('packages', ['type' => 'file', 'label' => __('Packages: ')]) ?>
    <?= $this->Form->control('profiles', ['type' => 'file', 'label' => __('Profiles: ')]) ?>
    <?= $this->Form->control('hosts', ['type' => 'file', 'label' => __('Hosts: ')]) ?>
    <?= $this->Form->button(__('Import')) ?>
    <?= $this->Form->end() ?>
</fieldset>
<fieldset>
    <legend><?= __('Export') ?></legend>
    <?= $this->Html->link(__('Packages'), '/packages.xml') ?><br />
    <?= $this->Html->link(__('Profiles'), '/profiles.xml') ?><br />
    <?= $this->Html->link(__('Hosts'), '/hosts.xml') ?><br />
</fieldset>
<h2><?= __('Settings') ?></h2><hr class="hbar" />
<p><?= __('Settings come from environment variables and take effect when the application restarts.') ?></p>
<fieldset class="systemsettings">
    <legend><?= __('Web Authentication') ?></legend>
    <div class="inputwrap"><label><?= __('Username:') ?></label><?= h($settings['adminUser']) ?></div>
    <div class="inputwrap"><label><?= __('Force SSL:') ?></label><?= $yesNo($settings['forceSsl']) ?></div>
</fieldset>
<fieldset class="xmlsettings">
    <legend><?= __('XML Feed') ?></legend>
    <div class="inputwrap"><label><?= __('Export disabled items:') ?></label><?= $yesNo($settings['xml']['exportDisabled']) ?></div>
    <div class="inputwrap"><label><?= __('Format XML output:') ?></label><?= $yesNo($settings['xml']['format']) ?></div>
    <div class="inputwrap"><label><?= __('Protect XML output:') ?></label><?= $yesNo($settings['xml']['protect']) ?></div>
    <?php if ($settings['xml']['protect']) : ?>
        <div class="inputwrap"><label><?= __('Username:') ?></label><?= h($settings['xml']['user']) ?></div>
    <?php endif; ?>
</fieldset>
<fieldset class="reportsettings">
    <legend><?= __('Reports') ?></legend>
    <div class="inputwrap"><label><?= __('Directory:') ?></label><?= $settings['reportsPath'] === '' ? __('Not configured') : h($settings['reportsPath']) ?></div>
</fieldset>
