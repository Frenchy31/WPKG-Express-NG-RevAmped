<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Package> $packages
 */

use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;

$this->assign('title', 'Packages');
?>
<h2>Packages - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($packages) === 0) : ?>
    <div class="message">No Packages found</div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
                <th scope="col"><?= $this->Paginator->sort('name', 'Name') ?></th>
                <th scope="col"><?= $this->Paginator->sort('id_text', 'Id') ?></th>
                <th scope="col"><?= $this->Paginator->sort('revision', 'Revision') ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('priority', 'Priority') ?></th>
                <th scope="col"><?= $this->Paginator->sort('reboot', 'Reboot') ?></th>
                <th scope="col"><?= $this->Paginator->sort('execute', 'Execute') ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('notify', 'Notify') ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
                <th scope="col" class="col-short">Actions</th>
            </tr>
            <?php foreach ($packages as $package) : ?>
                <tr<?= $package->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $this->element('enabled_toggle', ['record' => $package]) ?></td>
                    <td><?= $this->Html->link($package->name, ['action' => 'view', $package->id]) ?></td>
                    <td><?= h($package->id_text) ?></td>
                    <td><?= h($package->revision) ?></td>
                    <td class="col-short"><?= h($package->priority) ?></td>
                    <td><?= h(PackageReboot::tryFrom($package->reboot)?->label() ?? 'Unknown') ?></td>
                    <td><?= h(PackageExecute::tryFrom($package->execute)?->name ?? 'Unknown') ?></td>
                    <td class="col-short"><?= $package->notify ? 'Yes' : 'No' ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($package->modified) ?></div></td>
                    <td class="col-short">
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $package->id], 'Edit') ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $package->id], 'Delete', sprintf("Delete Package '%s'?", $package->name)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
