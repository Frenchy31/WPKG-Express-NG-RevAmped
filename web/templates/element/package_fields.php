<?php
/**
 * @var \App\View\AppView $this
 * @var bool $isEdit Editing an existing package.
 */

use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;
use App\View\Tooltip;

$executeOptions = [];
foreach (PackageExecute::cases() as $case) {
    $executeOptions[$case->value] = $case->name;
}
$rebootOptions = [];
foreach (PackageReboot::cases() as $case) {
    $rebootOptions[$case->value] = $case->label();
}

echo $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label(__('Name:'), Tooltip::packageName(), true)]);
echo $this->Form->control('id_text', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label(__('ID:'), Tooltip::packageId(), true)]);
echo $this->Form->control('revision', ['type' => 'text', 'class' => 'input', 'size' => 15, 'label' => $this->Wpkg->label(__('Revision:'), Tooltip::packageRevision(), $isEdit)]);
echo $this->Form->control('priority', ['type' => 'text', 'class' => 'input', 'size' => 5, 'label' => $this->Wpkg->label(__('Priority:'), Tooltip::packagePriority(), $isEdit)]);
echo $this->Form->control('execute', ['type' => 'select', 'options' => $executeOptions, 'label' => $this->Wpkg->label(__('Execute:'), Tooltip::packageExecute())]);
echo $this->Form->control('reboot', ['type' => 'select', 'options' => $rebootOptions, 'label' => $this->Wpkg->label(__('Reboot:'), Tooltip::packageReboot())]);
echo $this->Form->control('notify', ['type' => 'checkbox', 'nestedInput' => false, 'label' => $this->Wpkg->label(__('Notify:'), Tooltip::packageNotify())]);
echo $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label(__('Notes:'), Tooltip::packageNotes())]);
