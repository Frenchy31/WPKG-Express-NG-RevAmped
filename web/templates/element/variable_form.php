<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Variable $variable
 */
echo $this->Form->create($variable);
echo $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 15, 'label' => $this->Wpkg->label(__('Name:'), null, true)]);
echo $this->Form->control('value', ['type' => 'text', 'class' => 'input', 'size' => 45, 'label' => $this->Wpkg->label(__('Value:'), null, true)]);
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button(__('Submit')) . '</div>';
echo $this->Form->end();
