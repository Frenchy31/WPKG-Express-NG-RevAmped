<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Package $package
 * @var array<\App\Model\Entity\PackageCheck> $checks
 */

use App\Model\Enum\ActionType;
use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;
use App\View\Tooltip;

$this->assign('title', __('Package {0}', $package->name));

$packageLinks = function (array $packages): array {
    $links = [];
    foreach ($packages as $related) {
        $links[sprintf('%s (%s)', $related->name, $related->id_text)] = ['action' => 'view', $related->id];
    }

    return $links;
};
$profileLinks = [];
foreach ($package->profiles as $profile) {
    $profileLinks[$profile->id_text] = ['controller' => 'Profiles', 'action' => 'view', $profile->id];
}
?>
<h2><?= __('Package Details for \'\'{0}\'\'', h($package->name)) ?> - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $package->id], __('Edit')) ?>
    <?= $this->Wpkg->icon('var', ['controller' => 'Variables', 'action' => 'view', 'package', $package->id], __('Variables')) ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $package->id], __('Delete'), __('Delete Package \'\'{0}\'\'?', $package->name)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::packageEnabled()) ?>"><?= __('Enabled:') ?></label><?= $package->enabled ? __('Yes') : __('No') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageName()) ?>"><?= __('Name:') ?></label><?= h($package->name) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageId()) ?>"><?= __('ID:') ?></label><?= h($package->id_text) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageRevision()) ?>"><?= __('Revision:') ?></label><?= h($package->revision) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packagePriority()) ?>"><?= __('Priority:') ?></label><?= h($package->priority) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageReboot()) ?>"><?= __('Reboot:') ?></label><?= h(PackageReboot::tryFrom($package->reboot)?->label() ?? __('Unknown')) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageExecute()) ?>"><?= __('Execute:') ?></label><?= h(PackageExecute::tryFrom($package->execute)?->name ?? __('Unknown')) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageNotify()) ?>"><?= __('Notify:') ?></label><?= $package->notify ? __('True') : __('False') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageNotes()) ?>"><?= __('Notes:') ?></label><?= $this->element('notes', ['notes' => $package->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageLastmodified()) ?>"><?= __('Last Modified:') ?></label><div class="date"><?= $this->Wpkg->date($package->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageDependson()) ?>"><?= __('Depends On:') ?></label><?= $this->element('link_list', ['links' => $packageLinks($package->package_dependencies)]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageDependedonby()) ?>"><?= __('Depended On By:') ?></label><?= $this->element('link_list', ['links' => $packageLinks($package->depended_on_by)]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::packageProfiles()) ?>"><?= __('Exists in Profiles:') ?></label><?= $this->element('link_list', ['links' => $profileLinks]) ?></div>

<h2><?= __('Package Checks') ?> - [ <?= $this->Wpkg->icon('add', ['controller' => 'PackageChecks', 'action' => 'add', $package->id], __('Add')) ?> ]</h2><hr class="hbar" />
<div id="checks" class="clear">
    <?php if (!$checks) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <?= $this->element('check_tree', ['checks' => $checks]) ?>
    <?php endif; ?>
</div>

<h2><?= __('Package Actions') ?> - [ <?= $this->Wpkg->icon('add', ['controller' => 'PackageActions', 'action' => 'add', $package->id], __('Add')) ?> ]</h2><hr class="hbar" />
<div id="actions" class="clear">
    <?php if (!$package->package_actions) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th scope="col"><?= __('Type') ?></th>
                    <th scope="col"><?= __('Command') ?></th>
                    <th scope="col" class="col-short"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($package->package_actions as $action) : ?>
                    <tr>
                        <td><?= h(ActionType::tryFrom($action->type)?->name ?? __('Unknown')) ?></td>
                        <td><?= $this->Html->link($action->command, ['controller' => 'PackageActions', 'action' => 'view', $action->id], ['class' => 'mono']) ?></td>
                        <td class="col-short">
                            <?= $this->Wpkg->moveIcons(['controller' => 'PackageActions', 'action' => 'move', $action->id]) ?>
                            <?= $this->Wpkg->icon('pencil', ['controller' => 'PackageActions', 'action' => 'edit', $action->id], __('Edit')) ?>
                            <?= $this->Wpkg->postIcon('delete', ['controller' => 'PackageActions', 'action' => 'delete', $action->id], __('Delete'), __('Delete this action?')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
