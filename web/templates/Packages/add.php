<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', 'Add Package');
?>
<h2>Add New Package</h2><hr class="hbar" />
<?= $this->Form->create($package) ?>
<?= $this->element('package_fields', ['isEdit' => false]) ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button('Submit') ?></div>
<?= $this->Form->end() ?>
