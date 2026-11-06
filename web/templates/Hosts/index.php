<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Host> $hosts
 */
$this->assign('title', 'Hosts');
?>
<h2>Hosts - [ <?= $this->Wpkg->icon('add.png', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($hosts) === 0) : ?>
    <div class="message">No Hosts found</div>
<?php else : ?>
    <table cellpadding="0" cellspacing="0">
        <tr>
            <th width="68"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
            <th width="70"><?= $this->Paginator->sort('position', 'Position') ?></th>
            <th><?= $this->Paginator->sort('name', 'Name') ?></th>
            <th><?= $this->Paginator->sort('MainProfiles.id_text', 'Main Profile') ?></th>
            <th><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
            <th width="110">Actions</th>
        </tr>
        <?php foreach ($hosts as $host) : ?>
            <tr<?= $host->enabled ? '' : ' class="disabled"' ?>>
                <td><?= $this->element('enabled_toggle', ['record' => $host]) ?></td>
                <td><?= $host->position ?></td>
                <td><?= $this->Html->link($host->name, ['action' => 'view', $host->id]) ?></td>
                <td><?= $host->main_profile ? $this->Html->link($host->main_profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $host->main_profile->id]) : '' ?></td>
                <td><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></td>
                <td>
                    <?= $this->Wpkg->moveIcons(['action' => 'move', $host->id]) ?>
                    <?= $this->Wpkg->icon('pencil.png', ['action' => 'edit', $host->id], 'Edit') ?>
                    <?= $this->Wpkg->postIcon('delete.png', ['action' => 'delete', $host->id], 'Delete', sprintf("Delete Host '%s'?", $host->name)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?= $this->element('pagination') ?>
<?php endif; ?>
