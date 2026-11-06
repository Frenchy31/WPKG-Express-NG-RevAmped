<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */
$this->assign('title', 'Edit Package Action');
?>
<style>label {width: 80px;}</style>
<h2>Editing Package Action for '<?= $this->Html->link($action->package->name, ['controller' => 'Packages', 'action' => 'view', $action->package->id]) ?>'</h2><hr class="hbar" />
<?= $this->element('action_form') ?>
