<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */

use App\Model\Enum\ActionType;
use App\Model\Enum\ExitCodeReboot;
use App\View\Tooltip;

$this->assign('title', 'Package Action');
?>
<h2>Package Action Details for '<?= $this->Html->link($action->package->name, ['controller' => 'Packages', 'action' => 'view', $action->package->id]) ?>' - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $action->id], 'Edit') ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $action->id], 'Delete', 'Delete this action?') ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGEACTION_TYPE) ?>">Type:</label><?= h(ActionType::tryFrom($action->type)?->name ?? 'Unknown') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGEACTION_COMMAND) ?>">Command:</label><span class="mono"><?= h($action->command) ?></span></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGEACTION_TIMEOUT) ?>">Timeout:</label><?= h($action->timeout ?: '3600') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGEACTION_WORKDIR) ?>">Work Dir.:</label><?= $action->workdir ? '<span class="mono">' . h($action->workdir) . '</span>' : '&lt;None&gt;' ?></div>
<h2>Exit Codes - [ <?= $this->Wpkg->icon('add', ['controller' => 'ExitCodes', 'action' => 'add', $action->id], 'Add') ?> ]</h2><hr class="hbar" />
<div id="exitcodes" class="clear">
    <?php if (!$action->exit_codes) : ?>
        &lt;None&gt;
    <?php else : ?>
        <ul>
            <?php foreach ($action->exit_codes as $exitCode) : ?>
                <li>
                    <?= h($exitCode->code) ?> (Reboot: <?= h(ExitCodeReboot::tryFrom($exitCode->reboot)?->label() ?? 'Unknown') ?>)
                    [ <?= $this->Wpkg->icon('pencil', ['controller' => 'ExitCodes', 'action' => 'edit', $exitCode->id], 'Edit') ?>
                    <?= $this->Wpkg->postIcon('delete', ['controller' => 'ExitCodes', 'action' => 'delete', $exitCode->id], 'Delete') ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
