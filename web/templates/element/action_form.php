<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */

use App\Model\Enum\ActionType;
use App\View\Tooltip;

$typeOptions = [];
foreach (ActionType::cases() as $case) {
    $typeOptions[$case->value] = $case->name;
}

echo $this->Form->create($action);
echo $this->Form->control('type', ['type' => 'select', 'options' => $typeOptions, 'label' => $this->Wpkg->label('Type:', Tooltip::PACKAGEACTION_TYPE, true)]);
echo $this->Form->control('command', ['type' => 'text', 'class' => 'input', 'size' => 80, 'label' => $this->Wpkg->label('Command:', Tooltip::PACKAGEACTION_COMMAND, true)]);
echo $this->Form->control('timeout', ['type' => 'text', 'class' => 'input', 'size' => 8, 'label' => $this->Wpkg->label('Timeout:', Tooltip::PACKAGEACTION_TIMEOUT)]);
echo $this->Form->control('workdir', ['type' => 'text', 'class' => 'input', 'size' => 60, 'label' => $this->Wpkg->label('Work Dir.:', Tooltip::PACKAGEACTION_WORKDIR)]);
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button('Submit') . '</div>';
echo $this->Form->end();
