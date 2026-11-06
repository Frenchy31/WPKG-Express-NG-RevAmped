<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Profile> $profiles
 */
$this->assign('title', 'Profiles');
?>
<h2>Profiles - [ <?= $this->Wpkg->icon('add.png', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($profiles) === 0) : ?>
    <div class="message">No Profiles found</div>
<?php else : ?>
    <table cellpadding="0" cellspacing="0">
        <tr>
            <th width="68"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
            <th><?= $this->Paginator->sort('id_text', 'Id') ?></th>
            <th><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
            <th width="55">Actions</th>
        </tr>
        <?php foreach ($profiles as $profile) : ?>
            <tr<?= $profile->enabled ? '' : ' class="disabled"' ?>>
                <td><?= $this->element('enabled_toggle', ['record' => $profile]) ?></td>
                <td><?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?></td>
                <td><div class="date"><?= $this->Wpkg->date($profile->modified) ?></div></td>
                <td>
                    <?= $this->Wpkg->icon('pencil.png', ['action' => 'edit', $profile->id], 'Edit') ?>
                    <?= $this->Wpkg->postIcon('delete.png', ['action' => 'delete', $profile->id], 'Delete', sprintf("Delete Profile '%s'?", $profile->id_text)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?= $this->element('pagination') ?>
<?php endif; ?>
