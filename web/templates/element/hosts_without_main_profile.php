<?php
/**
 * @var \App\View\AppView $this
 * @var list<array{id: int, name: string, mainprofile_id: int}> $hosts
 */
?>
<?php if ($hosts) : ?>
    <h3><?= __('Hosts whose main profile does not exist') ?></h3>
    <p><?= __('The cleanup keeps these hosts. Open each one and choose an existing main profile.') ?></p>
    <div class="table-wrap">
        <table id="hosts-without-main-profile">
            <tr>
                <th scope="col"><?= __('Name') ?></th>
                <th scope="col"><?= __('Missing profile id') ?></th>
            </tr>
            <?php foreach ($hosts as $host) : ?>
                <?php $editUrl = ['controller' => 'Hosts', 'action' => 'edit', $host['id']]; ?>
                <tr>
                    <td><?= $this->Html->link($host['name'], $editUrl) ?></td>
                    <td><?= $host['mainprofile_id'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
