<?php
/**
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\PackageCheck> $checks Checks of one level.
 */
?>
<ul>
    <?php foreach ($checks as $check) : ?>
        <li>
            <span class="mono"><?= h($this->Wpkg->checkSummary($check)) ?></span>
            [ <?= $this->Wpkg->moveIcons(['controller' => 'PackageChecks', 'action' => 'move', $check->id]) ?>
            <?= $this->Wpkg->icon('pencil', ['controller' => 'PackageChecks', 'action' => 'edit', $check->id], 'Edit') ?>
            <?= $this->Wpkg->postIcon('delete', ['controller' => 'PackageChecks', 'action' => 'delete', $check->id], 'Delete', 'Delete this check and its children?') ?> ]<br />
            <?php if ($check->children) : ?>
                <?= $this->element('check_tree', ['checks' => $check->children]) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
