<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Package> $packages
 */

use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;

$this->assign('title', 'Packages');
?>
<h2>Packages - [ <?= $this->Wpkg->icon('add.png', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($packages) === 0) : ?>
    <div class="message">No Packages found</div>
<?php else : ?>
    <table cellpadding="0" cellspacing="0">
        <tr>
            <th width="68"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
            <th><?= $this->Paginator->sort('name', 'Name') ?></th>
            <th><?= $this->Paginator->sort('id_text', 'Id') ?></th>
            <th><?= $this->Paginator->sort('revision', 'Revision') ?></th>
            <th width="63"><?= $this->Paginator->sort('priority', 'Priority') ?></th>
            <th width="61"><?= $this->Paginator->sort('reboot', 'Reboot') ?></th>
            <th width="67"><?= $this->Paginator->sort('execute', 'Execute') ?></th>
            <th width="52"><?= $this->Paginator->sort('notify', 'Notify') ?></th>
            <th width="150"><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
            <th width="55">Actions</th>
        </tr>
        <?php foreach ($packages as $package) : ?>
            <tr<?= $package->enabled ? '' : ' class="disabled"' ?>>
                <td><?= $this->element('enabled_toggle', ['record' => $package]) ?></td>
                <td><?= $this->Html->link($package->name, ['action' => 'view', $package->id]) ?></td>
                <td><?= h($package->id_text) ?></td>
                <td><?= h($package->revision) ?></td>
                <td><?= h($package->priority) ?></td>
                <td><?= h(PackageReboot::tryFrom($package->reboot)?->label() ?? 'Unknown') ?></td>
                <td><?= h(PackageExecute::tryFrom($package->execute)?->name ?? 'Unknown') ?></td>
                <td><?= $package->notify ? 'Yes' : 'No' ?></td>
                <td><div class="date"><?= $this->Wpkg->date($package->modified) ?></div></td>
                <td>
                    <?= $this->Wpkg->icon('pencil.png', ['action' => 'edit', $package->id], 'Edit') ?>
                    <?= $this->Wpkg->postIcon('delete.png', ['action' => 'delete', $package->id], 'Delete', sprintf("Delete Package '%s'?", $package->name)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?= $this->element('pagination') ?>
<?php endif; ?>
