<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 */

use App\View\Tooltip;

$this->assign('title', 'Add Profile');
?>
<h2>Add New Profile</h2><hr class="hbar" />
<?= $this->Form->create($profile) ?>
<?= $this->Form->control('id_text', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label('ID:', Tooltip::PROFILE_ID, true)]) ?>
<?= $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label('Notes:', Tooltip::PROFILE_NOTES)]) ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button('Submit') ?></div>
<?= $this->Form->end() ?>
