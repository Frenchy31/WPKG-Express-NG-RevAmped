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
echo $this->Form->control('type', ['type' => 'select', 'options' => $typeOptions, 'label' => $this->Wpkg->label(__('Type:'), Tooltip::packageactionType(), true)]);
echo $this->Form->control('command', ['type' => 'text', 'class' => 'input mono', 'size' => 80, 'label' => $this->Wpkg->label(__('Command:'), Tooltip::packageactionCommand(), true)]);
echo $this->Form->control('timeout', ['type' => 'text', 'class' => 'input', 'size' => 8, 'label' => $this->Wpkg->label(__('Timeout:'), Tooltip::packageactionTimeout())]);
echo $this->Form->control('workdir', ['type' => 'text', 'class' => 'input mono', 'size' => 60, 'label' => $this->Wpkg->label(__('Work Dir.:'), Tooltip::packageactionWorkdir())]);
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button(__('Submit')) . '</div>';
echo $this->Form->end();
