<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 * @var array<int, string> $profiles
 */

use App\View\Tooltip;

$this->assign('title', __('Add Host'));
?>
<h2><?= __('Add New Host') ?></h2><hr class="hbar" />
<?= $this->Form->create($host) ?>
<?= $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label(__('Name:'), Tooltip::hostName(), true)]) ?>
<?= $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label(__('Notes:'), Tooltip::hostNotes())]) ?>
<?= $this->Form->control('mainprofile_id', ['type' => 'select', 'options' => $profiles, 'label' => $this->Wpkg->label(__('Main Profile:'), Tooltip::hostMainprofile())]) ?>
<div class="inputwrap"><label>&nbsp;</label><?= $this->Form->button(__('Submit')) ?></div>
<?= $this->Form->end() ?>
