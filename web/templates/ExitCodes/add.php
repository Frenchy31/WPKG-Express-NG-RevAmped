<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PackageAction $action
 */

use App\Model\Enum\ActionType;

$this->assign('title', 'Add Exit Code');
?>
<style>label {width: 80px;}</style>
<h2>Adding Exit Code to '<?= $this->Html->link(ActionType::tryFrom($action->type)?->name ?? 'Unknown', ['controller' => 'PackageActions', 'action' => 'view', $action->id], ['title' => $action->command]) ?>' Package Action</h2><hr class="hbar" />
<?= $this->element('exit_code_form') ?>
