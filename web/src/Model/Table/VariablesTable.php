<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\VariableType;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Variables attached to a package, a profile or a host.
 */
class VariablesTable extends Table
{
    /**
     * Declares the table.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('variables');
        $this->setDisplayField('name');
    }

    /**
     * Declares field validation.
     *
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->maxLength('name', 80)
            ->requirePresence('value', 'create')
            ->maxLength('value', 500);
    }

    /**
     * Returns the variables of a record.
     *
     * @param \App\Model\Enum\VariableType $type Owner type.
     * @param int $recordId Owner id.
     * @return array<\App\Model\Entity\Variable>
     */
    public function forRecord(VariableType $type, int $recordId): array
    {
        return $this->find()
            ->where(['ref_type' => $type->value, 'ref_id' => $recordId])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->toList();
    }
}
