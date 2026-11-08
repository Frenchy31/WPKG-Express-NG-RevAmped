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

$this->assign('title', 'Package ' . $package->name);

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
<h2>Package Details for '<?= h($package->name) ?>' - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $package->id], 'Edit') ?>
    <?= $this->Wpkg->icon('var', ['controller' => 'Variables', 'action' => 'view', 'package', $package->id], 'Variables') ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $package->id], 'Delete', sprintf("Delete Package '%s'?", $package->name)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_ENABLED) ?>">Enabled:</label><?= $package->enabled ? 'Yes' : 'No' ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_NAME) ?>">Name:</label><?= h($package->name) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_ID) ?>">ID:</label><?= h($package->id_text) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_REVISION) ?>">Revision:</label><?= h($package->revision) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_PRIORITY) ?>">Priority:</label><?= h($package->priority) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_REBOOT) ?>">Reboot:</label><?= h(PackageReboot::tryFrom($package->reboot)?->label() ?? 'Unknown') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_EXECUTE) ?>">Execute:</label><?= h(PackageExecute::tryFrom($package->execute)?->name ?? 'Unknown') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_NOTIFY) ?>">Notify:</label><?= $package->notify ? 'True' : 'False' ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_NOTES) ?>">Notes:</label><?= $this->element('notes', ['notes' => $package->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_LASTMODIFIED) ?>">Last Modified:</label><div class="date"><?= $this->Wpkg->date($package->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_DEPENDSON) ?>">Depends On:</label><?= $this->element('link_list', ['links' => $packageLinks($package->package_dependencies)]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_DEPENDEDONBY) ?>">Depended On By:</label><?= $this->element('link_list', ['links' => $packageLinks($package->depended_on_by)]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PACKAGE_PROFILES) ?>">Exists in Profiles:</label><?= $this->element('link_list', ['links' => $profileLinks]) ?></div>

<h2>Package Checks - [ <?= $this->Wpkg->icon('add', ['controller' => 'PackageChecks', 'action' => 'add', $package->id], 'Add') ?> ]</h2><hr class="hbar" />
<div id="checks" class="clear">
    <?php if (!$checks) : ?>
        &lt;None&gt;
    <?php else : ?>
        <?= $this->element('check_tree', ['checks' => $checks]) ?>
    <?php endif; ?>
</div>

<h2>Package Actions - [ <?= $this->Wpkg->icon('add', ['controller' => 'PackageActions', 'action' => 'add', $package->id], 'Add') ?> ]</h2><hr class="hbar" />
<div id="actions" class="clear">
    <?php if (!$package->package_actions) : ?>
        &lt;None&gt;
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Command</th>
                    <th scope="col" class="col-short">Actions</th>
                </tr>
                <?php foreach ($package->package_actions as $action) : ?>
                    <tr>
                        <td><?= h(ActionType::tryFrom($action->type)?->name ?? 'Unknown') ?></td>
                        <td><?= $this->Html->link($action->command, ['controller' => 'PackageActions', 'action' => 'view', $action->id], ['class' => 'mono']) ?></td>
                        <td class="col-short">
                            <?= $this->Wpkg->moveIcons(['controller' => 'PackageActions', 'action' => 'move', $action->id]) ?>
                            <?= $this->Wpkg->icon('pencil', ['controller' => 'PackageActions', 'action' => 'edit', $action->id], 'Edit') ?>
                            <?= $this->Wpkg->postIcon('delete', ['controller' => 'PackageActions', 'action' => 'delete', $action->id], 'Delete', 'Delete this action?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
