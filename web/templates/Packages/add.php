<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 */
$this->assign('title', __('Add Package'));
?>
<h2><?= __('Add New Package') ?></h2><hr class="hbar" />
<?= $this->Form->create($package) ?>
<?= $this->element('package_fields', ['isEdit' => false]) ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button(__('Submit')) ?></div>
<?= $this->Form->end() ?>
