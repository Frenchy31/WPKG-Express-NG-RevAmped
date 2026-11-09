<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $owner
 * @var string $ownerDisplayField
 * @var \App\Model\Enum\VariableType $variableType
 * @var array<\App\Model\Entity\Variable> $variables
 */
$this->assign('title', __('Variables'));
$ownerUrl = ['controller' => $variableType->tableName(), 'action' => 'view', $owner->get('id')];
?>
<h2><?= __('Variables for {0} \'\'{1}\'\'', h($variableType->name), $this->Html->link($owner->get($ownerDisplayField), $ownerUrl)) ?> - [
    <?= $this->Wpkg->icon('add', ['action' => 'add', strtolower($variableType->name), $owner->get('id')], __('Add')) ?>
]</h2><hr class="hbar" />
<div id="variables" class="clear">
    <?php if (!$variables) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <ul>
            <?php foreach ($variables as $variable) : ?>
                <li>
                    <?= h($variable->name) ?><span class="variable-equals"> = </span><?= h($variable->value) ?>
                    [ <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $variable->id], __('Edit')) ?>
                    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $variable->id], __('Delete')) ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
