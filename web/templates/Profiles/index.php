<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Profile> $profiles
 */
$this->assign('title', __('Profiles'));
?>
<h2><?= __('Profiles') ?> - [ <?= $this->Wpkg->icon('add', ['action' => 'add'], __('Add')) ?> ]</h2><hr class="hbar" />
<?php if (count($profiles) === 0) : ?>
    <div class="message"><?= __('No Profiles found') ?></div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short"><?= $this->Paginator->sort('enabled', __('Enabled')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('id_text', __('Id')) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', __('Last Modified')) ?></th>
                <th scope="col" class="col-short"><?= __('Actions') ?></th>
            </tr>
            <?php foreach ($profiles as $profile) : ?>
                <tr<?= $profile->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $this->element('enabled_toggle', ['record' => $profile]) ?></td>
                    <td><?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($profile->modified) ?></div></td>
                    <td class="col-short">
                        <?= $this->Wpkg->icon('pencil', ['action' => 'edit', $profile->id], __('Edit')) ?>
                        <?= $this->Wpkg->postIcon('delete', ['action' => 'delete', $profile->id], __('Delete'), __('Delete Profile \'\'{0}\'\'?', $profile->id_text)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?= $this->element('pagination') ?>
<?php endif; ?>
