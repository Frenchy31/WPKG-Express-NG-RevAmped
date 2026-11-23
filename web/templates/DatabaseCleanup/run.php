<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, int> $deleted
 * @var list<array{id: int, name: string, mainprofile_id: int}> $hosts
 */
$this->assign('title', __('Database cleanup'));
$total = array_sum($deleted);
?>
<h2><?= __('Database cleanup') ?></h2><hr class="hbar" />
<div class="message success">
    <?= __n('{0} orphan row deleted.', '{0} orphan rows deleted.', $total, (string)$total) ?>
</div>
<div class="table-wrap">
    <table id="deleted-counts">
        <tr>
            <th scope="col"><?= __('Table') ?></th>
            <th scope="col" class="col-short"><?= __('Rows deleted') ?></th>
        </tr>
        <?php foreach ($deleted as $tableName => $count) : ?>
            <tr>
                <td><code><?= h($tableName) ?></code></td>
                <td class="col-short"><?= $count ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?= $this->element('hosts_without_main_profile', ['hosts' => $hosts]) ?>
<p><?= $this->Html->link(__('Back to Admin'), ['controller' => 'Admin', 'action' => 'index']) ?></p>
