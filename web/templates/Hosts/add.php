<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 * @var array<int, string> $profiles
 */

use App\View\Tooltip;

$this->assign('title', 'Add Host');
?>
<style>label {width: 81px;}</style>
<h2>Add New Host</h2><hr class="hbar" />
<?= $this->Form->create($host) ?>
<?= $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label('Name:', Tooltip::HOST_NAME, true)]) ?>
<?= $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label('Notes:', Tooltip::HOST_NOTES)]) ?>
<?= $this->Form->control('mainprofile_id', ['type' => 'select', 'options' => $profiles, 'label' => $this->Wpkg->label('Main Profile:', Tooltip::HOST_MAINPROFILE)]) ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button('Submit') ?></div>
<?= $this->Form->end() ?>
