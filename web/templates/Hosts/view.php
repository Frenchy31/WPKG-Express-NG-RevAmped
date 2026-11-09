<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 */

use App\View\Tooltip;

$this->assign('title', __('Host {0}', $host->name));
?>
<h2><?= __('Host Details for \'\'{0}\'\'', h($host->name)) ?> - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $host->id], __('Edit')) ?>
    <?= $this->Wpkg->icon('var', ['controller' => 'Variables', 'action' => 'view', 'host', $host->id], __('Variables')) ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $host->id], __('Delete'), __('Delete Host \'\'{0}\'\'?', $host->name)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::hostEnabled()) ?>"><?= __('Enabled:') ?></label><?= $host->enabled ? __('Yes') : __('No') ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::hostName()) ?>"><?= __('Name:') ?></label><?= h($host->name) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::hostNotes()) ?>"><?= __('Notes:') ?></label><?= $this->element('notes', ['notes' => $host->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::hostLastmodified()) ?>"><?= __('Last Modified:') ?></label><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::hostMainprofile()) ?>"><?= __('Main Profile:') ?></label><?= $host->main_profile ? $this->Html->link($host->main_profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $host->main_profile->id]) : '' ?></div>
<h2><?= __('Additional associated Profiles') ?> - [ <?= $this->Wpkg->icon('pencil', ['action' => 'profiles', $host->id], __('Edit')) ?> ]</h2><hr class="hbar" />
<div id="profiles" class="clear">
    <?php if (!$host->profiles) : ?>
        <?= h(__('<None>')) ?>
    <?php else : ?>
        <ul>
            <?php foreach ($host->profiles as $profile) : ?>
                <li>
                    <?= $this->Html->link($profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $profile->id]) ?>
                    [ <?= $this->Wpkg->postIcon('delete', ['action' => 'removeProfile', $host->id, $profile->id], __('Remove')) ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
