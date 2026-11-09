<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 * @var array<int, string> $profiles
 */
$this->assign('title', __('Host profiles'));
?>
<h2><?= __('Modifying additional associated Profiles for Host \'\'{0}\'\'', $this->Html->link($host->name, ['action' => 'view', $host->id])) ?></h2><hr class="hbar" />
<?= $this->Form->create($host) ?>
<div class="hostsselect">
    <?= $this->Form->control('profiles._ids', ['label' => false, 'multiple' => 'checkbox', 'options' => $profiles]) ?>
</div>
<?= $this->Form->button(__('Submit')) ?>
<?= $this->Form->end() ?>
