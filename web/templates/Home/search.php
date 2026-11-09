<?php
/**
 * @var \App\View\AppView $this
 * @var string $searchText
 * @var array<string, array<\Cake\Datasource\EntityInterface>> $results
 * @var array<string, array<string>> $searchFields
 */
$this->assign('title', __('Search'));
$tableLabels = ['Packages' => __('Packages'), 'Profiles' => __('Profiles'), 'Hosts' => __('Hosts')];
$fieldLabels = ['id_text' => __('Id'), 'name' => __('Name'), 'notes' => __('Notes')];
$total = array_sum(array_map('count', $results));
?>
<?php if ($searchText === '') : ?>
    <h1><?= __('Unable to complete your search request') ?></h1>
<?php else : ?>
    <br /><?= __n('Found <b>{0} result</b> while searching for "<b>{1}</b>":', 'Found <b>{0} results</b> while searching for "<b>{1}</b>":', $total, (string)$total, h($searchText)) ?>
    <br />
    <?php foreach ($results as $tableName => $records) : ?>
        <h1><?= h($tableLabels[$tableName] ?? $tableName) ?> (<?= count($records) ?>)</h1>
        <ol>
            <?php foreach ($records as $record) : ?>
                <li>
                    <?= $this->Html->link($record->get($searchFields[$tableName][0]), ['controller' => $tableName, 'action' => 'view', $record->id]) ?>
                    <ul class="search-fields">
                        <?php foreach ($searchFields[$tableName] as $field) : ?>
                            <?php if (stripos((string)$record->get($field), $searchText) !== false) : ?>
                                <li><b><?= $fieldLabels[$field] ?? ucfirst($field) ?>:</b> <?= $this->Text->highlight(h($record->get($field)), h($searchText)) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ol><br />
    <?php endforeach; ?>
<?php endif; ?>
