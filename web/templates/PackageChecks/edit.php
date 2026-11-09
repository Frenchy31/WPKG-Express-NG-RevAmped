<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', __('Edit Package Check'));
?>
<h2><?= __('Editing Package Check for \'\'{0}\'\'', $this->Html->link($package->name, ['controller' => 'Packages', 'action' => 'view', $package->id])) ?></h2><hr class="hbar" />
<?= $this->element('check_form') ?>
