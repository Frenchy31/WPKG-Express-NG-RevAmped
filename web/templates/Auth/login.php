<?php
/**
 * @var \App\View\AppView $this
 */
?>
<?= $this->Form->create() ?>
<fieldset>
    <legend>wpkgExpress-ng Login</legend>
    <?= $this->Form->control('username', ['label' => 'Login: ', 'autocomplete' => 'off', 'autofocus' => true]) ?>
    <?= $this->Form->control('password', ['label' => 'Password: ']) ?>
    <hr />
    <?= $this->Form->button('Login') ?>
</fieldset>
<?= $this->Form->end() ?>
