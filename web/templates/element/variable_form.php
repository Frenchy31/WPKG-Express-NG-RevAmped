<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Variable $variable
 */
echo $this->Form->create($variable);
echo $this->Form->control('name', ['type' => 'text', 'class' => 'input', 'size' => 15, 'label' => $this->Wpkg->label('Name:', null, true)]);
echo $this->Form->control('value', ['type' => 'text', 'class' => 'input', 'size' => 45, 'label' => $this->Wpkg->label('Value:', null, true)]);
echo '<div class="inputwrap"><label>&nbsp;</label>' . $this->Form->button('Submit') . '</div>';
echo $this->Form->end();
