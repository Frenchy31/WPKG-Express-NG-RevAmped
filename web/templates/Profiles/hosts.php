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
    <div class="inputwrap inline">
        <label for="host-id">Add Host:</label>
        <?= $this->Form->select('host_id', $availableHosts, ['id' => 'host-id']) ?>
        <?= $this->Form->button('Add') ?>
    </div>
    <?= $this->Form->end() ?>
<?php endif; ?>
<?php if (!$attachedHosts) : ?>
    <div class="message">No Hosts found</div>
<?php else : ?>
    <div class="table-wrap">
        <table>
            <tr>
                <th scope="col" class="col-short">Enabled</th>
                <th scope="col" class="col-short">Position</th>
                <th scope="col">Name</th>
                <th scope="col">Link</th>
                <th scope="col">Last Modified</th>
                <th scope="col" class="col-short">Actions</th>
            </tr>
            <?php foreach ($attachedHosts as $attached) : ?>
                <?php $host = $attached['host']; ?>
                <tr<?= $host->enabled ? '' : ' class="disabled"' ?>>
                    <td class="col-short"><?= $host->enabled ? 'Yes' : 'No' ?></td>
                    <td class="col-short"><?= h($host->position) ?></td>
                    <td><?= $this->Html->link($host->name, ['controller' => 'Hosts', 'action' => 'view', $host->id]) ?></td>
                    <td><?= h($attached['link']) ?></td>
                    <td><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></td>
                    <td class="col-short">
                        <?php if ($attached['link'] === 'Main') : ?>
                            <?= $this->Wpkg->icon('pencil', ['controller' => 'Hosts', 'action' => 'edit', $host->id], 'Change the main profile') ?>
                        <?php else : ?>
                            <?= $this->Wpkg->postIcon('delete', ['action' => 'removeHost', $profile->id, $host->id], 'Remove', sprintf("Remove Host '%s' from Profile '%s'? At their next synchronization, clients uninstall the packages they only got from this profile.", $host->name, $profile->id_text)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>
