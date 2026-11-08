<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Host $host
 */

use App\View\Tooltip;

$this->assign('title', 'Host ' . $host->name);
?>
<h2>Host Details for '<?= h($host->name) ?>' - [
    <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $host->id], 'Edit') ?>
    <?= $this->Wpkg->icon('var', ['controller' => 'Variables', 'action' => 'view', 'host', $host->id], 'Variables') ?>
    <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $host->id], 'Delete', sprintf("Delete Host '%s'?", $host->name)) ?>
]</h2><hr class="hbar" />
<div class="inputwrap"><label title="<?= h(Tooltip::HOST_ENABLED) ?>">Enabled:</label><?= $host->enabled ? 'Yes' : 'No' ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::HOST_NAME) ?>">Name:</label><?= h($host->name) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::HOST_NOTES) ?>">Notes:</label><?= $this->element('notes', ['notes' => $host->notes]) ?></div>
<div class="inputwrap"><label title="<?= h(Tooltip::HOST_LASTMODIFIED) ?>">Last Modified:</label><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></div>
<div class="inputwrap"><label title="<?= h(Tooltip::HOST_MAINPROFILE) ?>">Main Profile:</label><?= $host->main_profile ? $this->Html->link($host->main_profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $host->main_profile->id]) : '' ?></div>
<h2>Additional associated Profiles - [ <?= $this->Wpkg->icon('pencil', ['action' => 'profiles', $host->id], 'Edit') ?> ]</h2><hr class="hbar" />
<div id="profiles" class="clear">
    <?php if (!$host->profiles) : ?>
        &lt;None&gt;
    <?php else : ?>
        <ul>
            <?php foreach ($host->profiles as $profile) : ?>
                <li>
                    <?= $this->Html->link($profile->id_text, ['controller' => 'Profiles', 'action' => 'view', $profile->id]) ?>
                    [ <?= $this->Wpkg->postIcon('delete', ['action' => 'removeProfile', $host->id, $profile->id], 'Remove') ?> ]
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
