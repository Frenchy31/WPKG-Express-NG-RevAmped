<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Host> $hosts
 */
$this->assign('title', 'Hosts');
?>
<h2>Hosts - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($hosts) === 0) : ?>
    <div class="message">No Hosts found</div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('position', 'Position') ?></th>
                <th scope="col"><?= $this->Paginator->sort('name', 'Name') ?></th>
                <th scope="col"><?= $this->Paginator->sort('MainProfiles.id_text', 'Main Profile') ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
                <th scope="col" class="col-short">Actions</th>
            </tr>
            <?php foreach ($hosts as $host) : ?>
                <tr<?= $host->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $this->element('enabled_toggle', ['record' => $host]) ?></td>
                    <td class="col-short"><?= $host->position ?></td>
                    <td><?= $this->Html->link($host->name, ['action' => 'view', $host->id]) ?></td>
                    <td><?= $host->main_profile ? $this->Html->link($host->main_profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $host->main_profile->id]) : '' ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></td>
                    <td class="col-short">
                        <?= $this->Wpkg->moveIcons(['action' => 'move', $host->id]) ?>
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $host->id], 'Edit') ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $host->id], 'Delete', sprintf("Delete Host '%s'?", $host->name)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
