<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */

use App\Model\Enum\ActionType;

$this->assign('title', __('Edit Exit Code'));
?>
<h2><?= __('Editing Exit Code for \'\'{0}\'\' Package Action', $this->Html->link(ActionType::tryFrom($action->type)?->name ?? __('Unknown'), ['controller' => 'PackageActions', 'action' => 'view', $action->id], ['title' => $action->command])) ?></h2><hr class="hbar" />
<?= $this->element('exit_code_form') ?>
