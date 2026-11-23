<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, int> $counts
 * @var int $rowsToDelete
 * @var list<array{id: int, name: string, mainprofile_id: int}> $hosts
 */
$confirmation = __n(
    'Delete {0} orphan row? This cannot be undone.',
    'Delete {0} orphan rows? This cannot be undone.',
    $rowsToDelete,
    (string)$rowsToDelete,
);
?>
<fieldset class="databasecleanup">
    <legend><?= __('Database cleanup') ?></legend>
    <p><?= __('An orphan row points to a record that no longer exists. Nothing changes until you click Clean.') ?></p>
    <div class="table-wrap">
        <table id="orphan-counts">
            <tr>
                <th scope="col"><?= __('Table') ?></th>
                <th scope="col"><?= __('Checked columns') ?></th>
                <th scope="col" class="col-short"><?= __('Orphan rows') ?></th>
            </tr>
            <?php foreach ($counts as $tableName => $count) : ?>
                <tr>
                    <td><code><?= h($tableName) ?></code></td>
                    <td><code><?= h(\App\Cleanup\OrphanTable::from($tableName)->columns()) ?></code></td>
                    <td class="col-short"><?= $count ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if ($rowsToDelete === 0) : ?>
        <p><?= __('No orphan rows to delete.') ?></p>
    <?php else : ?>
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'DatabaseCleanup', 'action' => 'run'],
            'onsubmit' => 'return confirm(' . json_encode($confirmation, JSON_THROW_ON_ERROR) . ');',
        ]) ?>
        <?= $this->Form->button(__('Clean')) ?>
        <?= $this->Form->end() ?>
    <?php endif; ?>
    <?= $this->element('hosts_without_main_profile', ['hosts' => $hosts]) ?>
</fieldset>
