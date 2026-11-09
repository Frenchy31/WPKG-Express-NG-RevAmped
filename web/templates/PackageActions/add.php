<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', __('Add Package Action'));
?>
<h2><?= __('Adding Package Action for \'\'{0}\'\'', $this->Html->link($package->name, ['controller' => 'Packages', 'action' => 'view', $package->id])) ?></h2><hr class="hbar" />
<?= $this->element('action_form') ?>
