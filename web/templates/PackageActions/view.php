<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */

use App\Model\Enum\ActionType;
use App\Model\Enum\ExitCodeReboot;
use App\View\Tooltip;

$this->assign('title', __('Package Action'));
?>
<h2><?= __('Package Action Details for \'\'{0}\'\'', $this->Html->link($action->package->name, ['controller' => 'Packages', 'action' => 'view', $action->package->id])) ?> - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $action->id], __('Edit')) ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $action->id], __('Delete'), __('Delete this action?')) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::packageactionType()) ?>"><?= __('Type:') ?></label><?= h(ActionType::tryFrom($action->type)?->name ?? __('Unknown')) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageactionCommand()) ?>"><?= __('Command:') ?></label><span class="mono"><?= h($action->command) ?></span></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageactionTimeout()) ?>"><?= __('Timeout:') ?></label><?= h($action->timeout ?: '3600') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageactionWorkdir()) ?>"><?= __('Work Dir.:') ?></label><?= $action->workdir ? '<span class="mono">' . h($action->workdir) . '</span>' : h(__('<None>')) ?></div>
<h2><?= __('Exit Codes') ?> - [ <?= $this->Wpkg->icon('add', ['controller' => 'ExitCodes', 'action' => 'add', $action->id], __('Add')) ?> ]</h2><hr class="hbar" />
<div id="exitcodes" class="clear">
    <?php if (!$action->exit_codes) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <ul>
            <?php foreach ($action->exit_codes as $exitCode) : ?>
                <li>
                    <?= h($exitCode->code) ?> <?= h(__('(Reboot: {0})', ExitCodeReboot::tryFrom($exitCode->reboot)?->label() ?? __('Unknown'))) ?>
                    [ <?= $this->Wpkg->icon('pencil', ['controller' => 'ExitCodes', 'action' => 'edit', $exitCode->id], __('Edit')) ?>
                    <?= $this->Wpkg->postIcon('delete', ['controller' => 'ExitCodes', 'action' => 'delete', $exitCode->id], __('Delete')) ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
