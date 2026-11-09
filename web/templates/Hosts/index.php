<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Host> $hosts
 */
$this->assign('title', __('Hosts'));
?>
<h2><?= __('Hosts') ?> - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], __('Add')) ?> ]</h2><hr class="hbar" />
<?php if (count($hosts) === 0) : ?>
    <div class="message"><?= __('No Hosts found') ?></div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', __('Enabled')) ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('position', __('Position')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('name', __('Name')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('MainProfiles.id_text', __('Main Profile')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', __('Last Modified')) ?></th>
                <th scope="col" class="col-short"><?= __('Actions') ?></th>
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
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $host->id], __('Edit')) ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $host->id], __('Delete'), __('Delete Host \'\'{0}\'\'?', $host->name)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
