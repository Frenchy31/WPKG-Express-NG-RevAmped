<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 * @var array<int, string> $packages
 */
$this->assign('title', 'Profile packages');
?>
<h2>Modifying associated Packages for Profile '<?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?>'</h2><hr class="hbar" />
<?= $this->Form->create($profile) ?>
<div class="profilesselect">
    <?= $this->Form->control('packages._ids', ['label' => false, 'multiple' => 'checkbox', 'options' => $packages]) ?>
</div>
<?= $this->Form->button('Submit') ?>
<?= $this->Form->end() ?>
