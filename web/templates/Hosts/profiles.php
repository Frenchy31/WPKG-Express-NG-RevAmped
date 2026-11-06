<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 * @var array<int, string> $profiles
 */
$this->assign('title', 'Host profiles');
?>
<h2>Modifying additional associated Profiles for Host '<?= $this->Html->link($host->name, ['action' => 'view', $host->id]) ?>'</h2><hr class="hbar" />
<?= $this->Form->create($host) ?>
<div class="hostsselect">
    <?= $this->Form->control('profiles._ids', ['label' => false, 'multiple' => 'checkbox', 'options' => $profiles]) ?>
</div>
<?= $this->Form->button('Submit') ?>
<?= $this->Form->end() ?>
