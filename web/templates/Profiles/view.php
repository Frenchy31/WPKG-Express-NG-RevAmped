<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 */

use App\View\Tooltip;

$this->assign('title', __('Profile {0}', $profile->id_text));
$dependencyLinks = [];
foreach ($profile->profile_dependencies as $dependency) {
    $dependencyLinks[$dependency->id_text] = ['action' => 'view', $dependency->id];
}
?>
<h2><?= __('Profile Details for \'\'{0}\'\'', h($profile->id_text)) ?> - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $profile->id], __('Edit')) ?>
    <?= $this->Wpkg->icon('hosts', ['action' => 'hosts', $profile->id], __('Hosts')) ?>
    <?= $this->Wpkg->icon('var', ['controller' => 'Variables', 'action' => 'view', 'profile', $profile->id], __('Variables')) ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $profile->id], __('Delete'), __('Delete Profile \'\'{0}\'\'?', $profile->id_text)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::profileEnabled()) ?>"><?= __('Enabled:') ?></label><?= $profile->enabled ? __('Yes') : __('No') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::profileId()) ?>"><?= __('ID:') ?></label><?= h($profile->id_text) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::profileNotes()) ?>"><?= __('Notes:') ?></label><?= $this->element('notes', ['notes' => $profile->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::profileLastmodified()) ?>"><?= __('Last Modified:') ?></label><div class="date"><?= $this->Wpkg->date($profile->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::profileDependson()) ?>"><?= __('Depends On:') ?></label><?= $this->element('link_list', ['links' => $dependencyLinks]) ?></div>
<h2><?= __('Associated Packages') ?> - [ <?= $this->Wpkg->icon('pencil', ['action' => 'packages', $profile->id], __('Edit')) ?> ]</h2><hr class="hbar" />
<div id="packages" class="clear">
    <?php if (!$profile->packages) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <ul>
            <?php foreach ($profile->packages as $package) : ?>
                <li>
                    <?= $this->Html->link(sprintf('%s (%s)', $package->name, $package->id_text), ['controller' => 'Packages', 'action' => 'view', $package->id]) ?>
                    [ <?= $this->Wpkg->postIcon('delete', ['action' => 'removePackage', $profile->id, $package->id], __('Remove')) ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
