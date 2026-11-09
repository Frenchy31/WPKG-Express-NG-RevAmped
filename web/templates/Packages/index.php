<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Package> $packages
 */

use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;

$this->assign('title', __('Packages'));
?>
<h2><?= __('Packages') ?> - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], __('Add')) ?> ]</h2><hr class="hbar" />
<?php if (count($packages) === 0) : ?>
    <div class="message"><?= __('No Packages found') ?></div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', __('Enabled')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('name', __('Name')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('id_text', __('Id')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('revision', __('Revision')) ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('priority', __('Priority')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('reboot', __('Reboot')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('execute', __('Execute')) ?></th>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('notify', __('Notify')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', __('Last Modified')) ?></th>
                <th scope="col" class="col-short"><?= __('Actions') ?></th>
            </tr>
            <?php foreach ($packages as $package) : ?>
                <tr<?= $package->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $this->element('enabled_toggle', ['record' => $package]) ?></td>
                    <td><?= $this->Html->link($package->name, ['action' => 'view', $package->id]) ?></td>
                    <td><?= h($package->id_text) ?></td>
                    <td><?= h($package->revision) ?></td>
                    <td class="col-short"><?= h($package->priority) ?></td>
                    <td><?= h(PackageReboot::tryFrom($package->reboot)?->label() ?? __('Unknown')) ?></td>
                    <td><?= h(PackageExecute::tryFrom($package->execute)?->name ?? __('Unknown')) ?></td>
                    <td class="col-short"><?= $package->notify ? __('Yes') : __('No') ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($package->modified) ?></div></td>
                    <td class="col-short">
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $package->id], __('Edit')) ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $package->id], __('Delete'), __('Delete Package \'\'{0}\'\'?', $package->name)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
