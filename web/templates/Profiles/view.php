<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 */

use App\View\Tooltip;

$this->assign('title', 'Profile ' . $profile->id_text);
$dependencyLinks = [];
foreach ($profile->profile_dependencies as $dependency) {
    $dependencyLinks[$dependency->id_text] = ['action' => 'view', $dependency->id];
}
?>
<style>label {width: 114px;}</style>
<h2>Profile Details for '<?= h($profile->id_text) ?>' - [
    <?= $this->Wpkg->icon('pencil.png', ['action' => 'edit', $profile->id], 'Edit') ?>
    <?= $this->Wpkg->icon('hosts.png', ['action' => 'hosts', $profile->id], 'Hosts') ?>
    <?= $this->Wpkg->icon('var.png', ['controller' => 'Variables', 'action' => 'view', 'profile', $profile->id], 'Variables') ?>
    <?= $this->Wpkg->postIcon('delete.png', ['action' => 'delete', $profile->id], 'Delete', sprintf("Delete Profile '%s'?", $profile->id_text)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::PROFILE_ENABLED) ?>">Enabled:</label><?= $profile->enabled ? 'Yes' : 'No' ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PROFILE_ID) ?>">ID:</label><?= h($profile->id_text) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PROFILE_NOTES) ?>">Notes:</label><?= $this->element('notes', ['notes' => $profile->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PROFILE_LASTMODIFIED) ?>">Last Modified:</label><div class="date"><?= $this->Wpkg->date($profile->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::PROFILE_DEPENDSON) ?>">Depends On:</label><?= $this->element('link_list', ['links' => $dependencyLinks]) ?></div>
<h2>Associated Packages - [ <?= $this->Wpkg->icon('pencil.png', ['action' => 'packages', $profile->id], 'Edit') ?> ]</h2><hr class="hbar" />
<div id="packages" class="clear">
    <?php if (!$profile->packages) : ?>
        &lt;None&gt;
    <?php else : ?>
        <ul>
            <?php foreach ($profile->packages as $package) : ?>
                <li>
                    <?= $this->Html->link(sprintf('%s (%s)', $package->name, $package->id_text), ['controller' => 'Packages', 'action' => 'view', $package->id]) ?>
                    [ <?= $this->Wpkg->postIcon('delete.png', ['action' => 'removePackage', $profile->id, $package->id], 'Remove') ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
