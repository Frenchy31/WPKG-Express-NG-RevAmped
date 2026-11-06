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

echo $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label('Name:', Tooltip::PACKAGE_NAME, true)]);
echo $this->Form->control('id_text', ['type' => 'text', 'class' => 'input', 'size' => 40, 'label' => $this->Wpkg->label('ID:', Tooltip::PACKAGE_ID, true)]);
echo $this->Form->control('revision', ['type' => 'text', 'class' => 'input', 'size' => 15, 'label' => $this->Wpkg->label('Revision:', Tooltip::PACKAGE_REVISION, $isEdit)]);
echo $this->Form->control('priority', ['type' => 'text', 'class' => 'input', 'size' => 5, 'label' => $this->Wpkg->label('Priority:', Tooltip::PACKAGE_PRIORITY, $isEdit)]);
echo $this->Form->control('execute', ['type' => 'select', 'options' => $executeOptions, 'label' => $this->Wpkg->label('Execute:', Tooltip::PACKAGE_EXECUTE)]);
echo $this->Form->control('reboot', ['type' => 'select', 'options' => $rebootOptions, 'label' => $this->Wpkg->label('Reboot:', Tooltip::PACKAGE_REBOOT)]);
echo $this->Form->control('notify', ['type' => 'checkbox', 'nestedInput' => false, 'label' => $this->Wpkg->label('Notify:', Tooltip::PACKAGE_NOTIFY)]);
echo $this->Form->control('notes', ['type' => 'textarea', 'cols' => 30, 'rows' => 4, 'label' => $this->Wpkg->label('Notes:', Tooltip::PACKAGE_NOTES)]);
