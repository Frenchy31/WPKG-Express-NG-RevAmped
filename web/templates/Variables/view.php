<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $owner
 * @var string $ownerDisplayField
 * @var \App\Model\Enum\VariableType $variableType
 * @var array<\App\Model\Entity\Variable> $variables
 */
$this->assign('title', 'Variables');
$ownerUrl = ['controller' => $variableType->tableName(), 'action' => 'view', $owner->get('id')];
?>
<h2>Variables for <?= h($variableType->name) ?> '<?= $this->Html->link($owner->get($ownerDisplayField), $ownerUrl) ?>' - [
    <?= $this->Wpkg->icon('add', ['action' => 'add', strtolower($variableType->name), $owner->get('id')], 'Add') ?>
]</h2><hr class="hbar" />
<div id="variables" class="clear">
    <?php if (!$variables) : ?>
        &lt;None&gt;
    <?php else : ?>
        <ul>
            <?php foreach ($variables as $variable) : ?>
                <li>
                    <?= h($variable->name) ?><span class="variable-equals"> = </span><?= h($variable->value) ?>
                    [ <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $variable->id], 'Edit') ?>
                    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $variable->id], 'Delete') ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
