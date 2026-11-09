<?php
/**
 * @var \App\View\AppView $this
 */
?>
<?= $this->Form->create() ?>
<fieldset>
    <legend><?= __('wpkgExpress-ng Login') ?></legend>
    <?= $this->Form->control('username', ['label' => __('Login: '), 'autocomplete' => 'off', 'autofocus' => true]) ?>
    <?= $this->Form->control('password', ['label' => __('Password: ')]) ?>
    <hr />
    <?= $this->Form->button(__('Login')) ?>
</fieldset>
<?= $this->Form->end() ?>
