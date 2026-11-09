<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */
$this->assign('title', __('Edit Package Action'));
?>
<h2><?= __('Editing Package Action for \'\'{0}\'\'', $this->Html->link($action->package->name, ['controller' => 'Packages', 'action' => 'view', $action->package->id])) ?></h2><hr class="hbar" />
<?= $this->element('action_form') ?>
