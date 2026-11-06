<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 * @var array<int, string> $profileDependencies
 */

use App\View\Tooltip;

$this->assign('title', 'Edit Profile');
?>
<style>label {width: 93px;}</style>
<h2>Editing Profile '<?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?>'</h2><hr class="hbar" />
<?= $this->Form->create($profile) ?>
<?= $this->Form->control('enabled', ['type' => 'checkbox', 'nestedInput' => false, 'label' => $this->Wpkg->label('Enabled:', Tooltip::PROFILE_ENABLED)]) ?>
<?= $this->Form->control('id_text', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label('ID:', Tooltip::PROFILE_ID, true)]) ?>
<?= $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label('Notes:', Tooltip::PROFILE_NOTES)]) ?>
<?php if ($profileDependencies) : ?>
    <?= $this->Form->control('profile_dependencies._ids', ['type' => 'select', 'multiple' => true, 'options' => $profileDependencies, 'label' => $this->Wpkg->label('Dependencies:', Tooltip::PROFILE_DEPENDSON)]) ?>
<?php else : ?>
    <div class="inputwrap"><label>Dependencies:</label>No other Profiles to choose from.</div>
<?php endif; ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button('Submit') ?></div>
<?= $this->Form->end() ?>
