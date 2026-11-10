<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Report\IndexState;
use Cake\ORM\Table;

/**
 * One row: the last pass of the reports indexer.
 */
class ReportIndexStateTable extends Table
{
    private const int ROW_ID = 1;

    /**
     * Declares the table.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('report_index_state');
    }

    /**
     * Returns the last pass.
     *
     * @return \App\Report\IndexState|null Null when no pass ever finished.
     */
    public function latest(): ?IndexState
    {
        $row = $this->find()->where(['id' => self::ROW_ID])->disableHydration()->first();

        return $row === null ? null : IndexState::fromRow($row);
    }

    /**
     * Records the end of a pass.
     *
     * @param \App\Report\IndexState $state The pass.
     * @return void
     */
    public function record(IndexState $state): void
    {
        $values = $state->toRow();
        if ($this->find()->where(['id' => self::ROW_ID])->count() > 0) {
            $this->updateAll($values, ['id' => self::ROW_ID]);

            return;
        }

        $this->insertQuery()->insert(array_keys($values + ['id' => self::ROW_ID]))
            ->values($values + ['id' => self::ROW_ID])
            ->execute();
    }
}
