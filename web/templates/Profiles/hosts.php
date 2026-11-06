<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 * @var array<int, array{host: \App\Model\Entity\Host, link: string}> $attachedHosts
 * @var array<int, string> $availableHosts
 */
$this->assign('title', 'Hosts of Profile ' . $profile->id_text);
?>
<h2>Hosts of Profile '<?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?>'</h2><hr class="hbar" />
<?php if ($availableHosts) : ?>
    <?= $this->Form->create(null, ['url' => ['action' => 'addHost', $profile->id]]) ?>
    <div class="inputwrap">
        <label for="host-id" style="width: 70px">Add Host:</label>
        <?= $this->Form->select('host_id', $availableHosts, ['id' => 'host-id']) ?>
        <?= $this->Form->button('Add') ?>
    </div>
    <?= $this->Form->end() ?>
<?php endif; ?>
<?php if (!$attachedHosts) : ?>
    <div class="message">No Hosts found</div>
<?php else : ?>
    <table cellpadding="0" cellspacing="0">
        <tr>
            <th width="68">Enabled</th>
            <th width="70">Position</th>
            <th>Name</th>
            <th>Link</th>
            <th>Last Modified</th>
            <th width="55">Actions</th>
        </tr>
        <?php foreach ($attachedHosts as $attached) : ?>
            <?php $host = $attached['host']; ?>
            <tr<?= $host->enabled ? '' : ' class="disabled"' ?>>
                <td><?= $host->enabled ? 'Yes' : 'No' ?></td>
                <td><?= h($host->position) ?></td>
                <td><?= $this->Html->link($host->name, ['controller' => 'Hosts', 'action' => 'view', $host->id]) ?></td>
                <td><?= h($attached['link']) ?></td>
                <td><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></td>
                <td>
                    <?php if ($attached['link'] === 'Main') : ?>
                        <?= $this->Wpkg->icon('pencil.png', ['controller' => 'Hosts', 'action' => 'edit', $host->id], 'Change the main profile') ?>
                    <?php else : ?>
                        <?= $this->Wpkg->postIcon('delete.png', ['action' => 'removeHost', $profile->id, $host->id], 'Remove', sprintf("Remove Host '%s' from Profile '%s'? At their next synchronization, clients uninstall the packages they only got from this profile.", $host->name, $profile->id_text)) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
