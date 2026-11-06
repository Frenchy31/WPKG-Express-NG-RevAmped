<?php
/**
 * @var \App\View\AppView $this
 * @var string $searchText
 * @var array<string, array<\Cake\Datasource\EntityInterface>> $results
 * @var array<string, array<string>> $searchFields
 */
$this->assign('title', 'Search');
$total = array_sum(array_map('count', $results));
?>
<?php if ($searchText === '') : ?>
    <h1>Unable to complete your search request</h1>
<?php else : ?>
    <br />Found <b><?= $total ?> result<?= $total === 1 ? '' : 's' ?></b> while searching for "<b><?= h($searchText) ?></b>":
    <br />
    <?php foreach ($results as $tableName => $records) : ?>
        <h1><?= h($tableName) ?> (<?= count($records) ?>)</h1>
        <ol>
            <?php foreach ($records as $record) : ?>
                <li>
                    <?= $this->Html->link($record->get($searchFields[$tableName][0]), ['controller' => $tableName, 'action' => 'view', $record->id]) ?>
                    <ul style="list-style-type: none; list-style-image: none">
                        <?php foreach ($searchFields[$tableName] as $field) : ?>
                            <?php if (stripos((string)$record->get($field), $searchText) !== false) : ?>
                                <li><b><?= $field === 'id_text' ? 'Id' : ucfirst($field) ?>:</b> <?= $this->Text->highlight(h($record->get($field)), h($searchText)) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ol><br />
    <?php endforeach; ?>
<?php endif; ?>
