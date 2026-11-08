<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 * @var array<int, string> $packageDependencies
 */

use App\View\Tooltip;

$this->assign('title', 'Edit Package');
?>
<h2>Editing Package '<?= $this->Html->link($package->name, ['action' => 'view', $package->id]) ?>'</h2><hr class="hbar" />
<?= $this->Form->create($package) ?>
<?= $this->Form->control('enabled', ['type' => 'checkbox', 'nestedInput' => false, 'label' => $this->Wpkg->label('Enabled:', Tooltip::PACKAGE_ENABLED)]) ?>
<?= $this->element('package_fields', ['isEdit' => true]) ?>
<?php if ($packageDependencies) : ?>
    <?= $this->Form->control('package_dependencies._ids', ['type' => 'select', 'multiple' => true, 'options' => $packageDependencies, 'label' => $this->Wpkg->label('Dependencies:', Tooltip::PACKAGE_DEPENDSON)]) ?>
<?php else : ?>
    <div class="inputwrap"><label>Dependencies:</label>No other Packages to choose from.</div>
<?php endif; ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button('Save') ?></div>
<?= $this->Form->end() ?>
