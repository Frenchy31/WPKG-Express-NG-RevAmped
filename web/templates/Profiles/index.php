<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Profile> $profiles
 */
$this->assign('title', 'Profiles');
?>
<h2>Profiles - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], 'Add') ?> ]</h2><hr class="hbar" />
<?php if (count($profiles) === 0) : ?>
    <div class="message">No Profiles found</div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', 'Enabled') ?></th>
                <th scope="col"><?= $this->Paginator->sort('id_text', 'Id') ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', 'Last Modified') ?></th>
                <th scope="col" class="col-short">Actions</th>
            </tr>
            <?php foreach ($profiles as $profile) : ?>
                <tr<?= $profile->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $this->element('enabled_toggle', ['record' => $profile]) ?></td>
                    <td><?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($profile->modified) ?></div></td>
                    <td class="col-short">
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $profile->id], 'Edit') ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $profile->id], 'Delete', sprintf("Delete Profile '%s'?", $profile->id_text)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
