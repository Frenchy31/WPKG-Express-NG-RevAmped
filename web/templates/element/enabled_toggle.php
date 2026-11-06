<?php
/**
 * @var \App\View\AppView $this
 * @var object $record Record with `id` and `enabled`.
 */
echo $this->Form->postLink(
    $record->enabled ? 'Yes' : 'No',
    ['action' => $record->enabled ? 'disable' : 'enable', $record->id],
);
