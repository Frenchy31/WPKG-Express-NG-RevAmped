<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, array<string, mixed>> $statistics
 */
$this->assign('title', 'Summary');
$blockClasses = ['Packages' => 'statsblockLeft', 'Profiles' => 'statsblockRight', 'Hosts' => 'statsblockLeft endOfRow'];
?>
<div id="stats-container">
    <?php foreach ($statistics as $tableName => $statistic) : ?>
        <div class="<?= $blockClasses[$tableName] ?>">
            <h2><?= h($tableName) ?></h2><hr class="hbar" />
            <div class="inputwrap"><label style="width: 90px"># Enabled:</label><?= $statistic['enabled'] ?></div>
            <div class="inputwrap"><label style="width: 90px"># Disabled:</label><?= $statistic['disabled'] ?></div>
            <div class="inputwrap"><label style="width: 90px"># Total:</label><?= $statistic['total'] ?></div>
            <div class="inputwrap"><label style="width: 90px">Last Modified:</label><ol class="reg">
                <?php foreach ($statistic['recent'] as $record) : ?>
                    <li>
                        <?= $this->Html->link($record->get($statistic['displayField']), ['controller' => $tableName, 'action' => 'view', $record->id]) ?>
                        (<div class="date"><?= $this->Wpkg->date($record->modified) ?></div>)
                    </li>
                <?php endforeach; ?>
            </ol></div>
        </div>
    <?php endforeach; ?>
</div>
