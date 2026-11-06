<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Profile $profile
 * @var array<int, array{host: \App\Model\Entity\Host, link: string}> $attachedHosts
 */
$this->assign('title', 'Hosts of Profile ' . $profile->id_text);
?>
<h2>Hosts of Profile '<?= $this->Html->link($profile->id_text, ['action' => 'view', $profile->id]) ?>'</h2><hr class="hbar" />
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
        </tr>
        <?php foreach ($attachedHosts as $attached) : ?>
            <?php $host = $attached['host']; ?>
            <tr<?= $host->enabled ? '' : ' class="disabled"' ?>>
                <td><?= $host->enabled ? 'Yes' : 'No' ?></td>
                <td><?= h($host->position) ?></td>
                <td><?= $this->Html->link($host->name, ['controller' => 'Hosts', 'action' => 'view', $host->id]) ?></td>
                <td><?= h($attached['link']) ?></td>
                <td><div class="date"><?= $this->Wpkg->date($host->modified) ?></div></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
