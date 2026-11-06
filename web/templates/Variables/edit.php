<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $owner
 * @var string $ownerDisplayField
 * @var \App\Model\Enum\VariableType $variableType
 */
$this->assign('title', 'Editing Variable');
$ownerUrl = ['controller' => $variableType->tableName(), 'action' => 'view', $owner->get('id')];
?>
<style>label {width: 80px;}</style>
<h2>Editing Variable for <?= h($variableType->name) ?> '<?= $this->Html->link($owner->get($ownerDisplayField), $ownerUrl) ?>'</h2><hr class="hbar" />
<?= $this->element('variable_form') ?>
