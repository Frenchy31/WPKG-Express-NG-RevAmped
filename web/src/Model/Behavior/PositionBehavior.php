<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use InvalidArgumentException;

/**
 * Keeps records ordered through a `position` column, within an optional scope.
 *
 * Moves rewrite the positions of the whole scope, which also repairs gaps and duplicates
 * left by imported data.
 */
class PositionBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * - field: column holding the position.
     * - scope: columns delimiting a group of ordered records.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'field' => 'position',
        'scope' => [],
    ];

    /**
     * Appends new records at the end of their scope.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event.
     * @param \Cake\Datasource\EntityInterface $entity Record being saved.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity): void
    {
        if (!$entity->isNew()) {
            return;
        }

        $field = $this->getConfig('field');
        $query = $this->table()->find()->where($this->scopeConditions($entity));
        $highest = $query->select(['highest' => $query->func()->max($field)])
            ->disableHydration()
            ->first();

        $entity->set($field, (int)($highest['highest'] ?? 0) + 1);
    }

    /**
     * Moves a record inside its scope.
     *
     * @param \Cake\Datasource\EntityInterface $entity Record to move.
     * @param string $direction One of top, up, down, bottom.
     * @return void
     * @throws \InvalidArgumentException When the direction is unknown.
     */
    public function move(EntityInterface $entity, string $direction): void
    {
        $table = $this->table();
        $field = $this->getConfig('field');
        $primaryKey = $table->getPrimaryKey();
        assert(is_string($primaryKey));

        $orderedIds = $table->find()
            ->select([$primaryKey])
            ->where($this->scopeConditions($entity))
            ->orderBy([$field => 'ASC', $primaryKey => 'ASC'])
            ->all()
            ->extract($primaryKey)
            ->toList();

        $currentIndex = array_search($entity->get($primaryKey), $orderedIds, true);
        assert(is_int($currentIndex));
        $targetIndex = match ($direction) {
            'top' => 0,
            'up' => max(0, $currentIndex - 1),
            'down' => min(count($orderedIds) - 1, $currentIndex + 1),
            'bottom' => count($orderedIds) - 1,
            default => throw new InvalidArgumentException(sprintf('Unknown direction `%s`.', $direction)),
        };

        array_splice($orderedIds, $currentIndex, 1);
        array_splice($orderedIds, $targetIndex, 0, [$entity->get($primaryKey)]);

        $table->getConnection()->transactional(function () use ($table, $field, $primaryKey, $orderedIds): void {
            foreach ($orderedIds as $index => $recordId) {
                $table->updateAll([$field => $index + 1], [$primaryKey => $recordId]);
            }
        });
    }

    /**
     * Builds the conditions delimiting the scope of a record.
     *
     * @param \Cake\Datasource\EntityInterface $entity Record.
     * @return array<string, mixed>
     */
    protected function scopeConditions(EntityInterface $entity): array
    {
        $conditions = [];
        foreach ($this->getConfig('scope') as $column) {
            $conditions[$column] = $entity->get($column);
        }

        return $conditions;
    }
}
