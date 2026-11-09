<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 * @var array<int, string> $profileDependencies
 */

use App\View\Tooltip;

$this->assign('title', __('Edit Profile'));
?>
<h2><?= __('Editing Profile \'\'{0}\'\'', $this->Html->link($profile->id_text, ['action' => 'view', $profile->id])) ?></h2><hr class="hbar" />
<?= $this->Form->create($profile) ?>
<?= $this->Form->control('enabled', ['type' => 'checkbox', 'nestedInput' => false, 'label' => $this->Wpkg->label(__('Enabled:'), Tooltip::profileEnabled())]) ?>
<?= $this->Form->control('id_text', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label(__('ID:'), Tooltip::profileId(), true)]) ?>
<?= $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label(__('Notes:'), Tooltip::profileNotes())]) ?>
<?php if ($profileDependencies) : ?>
    <?= $this->Form->control('profile_dependencies._ids', ['type' => 'select', 'multiple' => true, 'options' => $profileDependencies, 'label' => $this->Wpkg->label(__('Dependencies:'), Tooltip::profileDependson())]) ?>
<?php else : ?>
    <div class="inputwrap"><label><?= __('Dependencies:') ?></label><?= __('No other Profiles to choose from.') ?></div>
<?php endif; ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button(__('Submit')) ?></div>
<?= $this->Form->end() ?>
