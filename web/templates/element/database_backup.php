<?php
/**
 * Export, import and reset of the database, with the warning about the workstations.
 *
 * @var \App\View\AppView $this
 */
?>
<?php
$warning = __('Packages missing from the new feeds will be uninstalled on the workstations at their next run.');
$importConfirm = __('Replace the whole content of the database with this file?') . ' ' . $warning;
$resetConfirm = __('Empty the database?') . ' ' . $warning;
?>
<h2><?= __('Database') ?></h2><hr class="hbar" />
<p><?= __('The database holds the hosts, profiles and packages with their settings. The reports of the workstations are not part of it: the indexer rebuilds them.') ?></p>
<div class="message warning"><?= h($warning) ?></div>
<fieldset>
    <legend><?= __('Export the database') ?></legend>
    <p><?= __('Download the structure and the data as an SQL file.') ?></p>
    <?= $this->Form->postButton(__('Export'), ['controller' => 'Database', 'action' => 'export']) ?>
</fieldset>
<fieldset>
    <legend><?= __('Import an export') ?></legend>
    <p><?= __('Replaces the whole content of the database with a file produced by the export, at the same schema version. If anything fails, the database stays as it was.') ?></p>
    <?= $this->Form->create(null, ['type' => 'file', 'url' => ['controller' => 'Database', 'action' => 'import']]) ?>
    <?= $this->Form->control(
        'backup',
        ['type' => 'file', 'label' => __('File: '), 'accept' => '.sql', 'required' => true],
    ) ?>
    <?= $this->Form->button(__('Import'), ['confirm' => $importConfirm]) ?>
    <?= $this->Form->end() ?>
</fieldset>
<fieldset>
    <legend><?= __('Reset the database') ?></legend>
    <p><?= __('Deletes every host, profile and package. Export the database first if you may need it again.') ?></p>
    <?= $this->Form->postButton(
        __('Reset'),
        ['controller' => 'Database', 'action' => 'reset'],
        ['confirm' => $resetConfirm],
    ) ?>
</fieldset>
