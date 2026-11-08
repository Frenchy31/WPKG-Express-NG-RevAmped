<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageCheck $check
 * @var \App\Model\Enum\CheckType $type
 * @var \App\Model\Enum\CheckCondition $condition
 * @var array<int, string> $conditionOptions
 * @var array<int, string>|null $parents
 */

use App\Model\Enum\CheckType;
use App\View\Tooltip;

$fields = $this->Wpkg->checkFormFields($condition);
$typeOptions = [];
foreach (CheckType::cases() as $case) {
    $typeOptions[$case->value] = $case->name;
}

echo $this->Form->create($check);
if ($parents === null) {
    echo '<div class="inputwrap"><label>Type:</label>Logical</div>';
    echo $this->Form->hidden('type', ['value' => $type->value]);
} else {
    echo $this->Form->control('type', [
        'type' => 'select',
        'options' => $typeOptions,
        'value' => $type->value,
        'label' => $this->Wpkg->label('Type:', Tooltip::PACKAGECHECK_TYPE, true),
        'onchange' => "window.location.search = '?type=' + this.value",
    ]);
}
echo $this->Form->control('condition', [
    'type' => 'select',
    'options' => $conditionOptions,
    'value' => $condition->value,
    'label' => $this->Wpkg->label('Condition:', Tooltip::PACKAGECHECK_CONDITION, true),
    'onchange' => sprintf("window.location.search = '?type=%d&cond=' + this.value", $type->value),
]);
if ($fields['showPath']) {
    echo $this->Form->control('path', [
        'type' => 'text',
        'class' => 'input mono',
        'size' => 60,
        'label' => $this->Wpkg->label($fields['pathLabel'], Tooltip::PACKAGECHECK_PATH, true),
    ]);
}
if ($fields['showValue']) {
    echo $this->Form->control('value', [
        'type' => 'text',
        'class' => 'input mono',
        'size' => 30,
        'label' => $this->Wpkg->label($fields['valueLabel'], Tooltip::PACKAGECHECK_VALUE, true),
    ]);
}
if ($parents !== null) {
    echo $this->Form->control('parent_id', [
        'type' => 'select',
        'options' => $parents,
        'empty' => false,
        'label' => $this->Wpkg->label('Parent:', Tooltip::PACKAGECHECK_PARENT, true),
    ]);
}
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button('Submit') . '</div>';
echo $this->Form->end();
