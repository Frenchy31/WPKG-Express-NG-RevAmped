<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', 'Add Package Action');
?>
<style>label {width: 80px;}</style>
<h2>Adding Package Action for '<?= $this->Html->link($package->name, ['controller' => 'Packages', 'action' => 'view', $package->id]) ?>'</h2><hr class="hbar" />
<?= $this->element('action_form') ?>
