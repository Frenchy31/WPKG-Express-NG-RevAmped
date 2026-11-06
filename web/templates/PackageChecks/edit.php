<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', 'Edit Package Check');
?>
<style>label {width: 136px;}</style>
<h2>Editing Package Check for '<?= $this->Html->link($package->name, ['controller' => 'Packages', 'action' => 'view', $package->id]) ?>'</h2><hr class="hbar" />
<?= $this->element('check_form') ?>
