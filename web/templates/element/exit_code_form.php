<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ExitCode $exitCode
 */

use App\Model\Enum\ExitCodeReboot;
use App\View\Tooltip;

$rebootOptions = [];
foreach (ExitCodeReboot::cases() as $case) {
    $rebootOptions[$case->value] = $case->label();
}

echo $this->Form->create($exitCode);
echo $this->Form->control('code', ['type' => 'text', 'class' => 'input', 'size' => 8, 'label' => $this->Wpkg->label(__('Code:'), Tooltip::exitcodeCode(), true)]);
echo $this->Form->control('reboot', ['type' => 'select', 'options' => $rebootOptions, 'label' => $this->Wpkg->label(__('Reboot:'), Tooltip::exitcodeReboot())]);
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button(__('Submit')) . '</div>';
echo $this->Form->end();
