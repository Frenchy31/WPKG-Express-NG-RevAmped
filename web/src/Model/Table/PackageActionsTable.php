<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\ActionType;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Commands of a package, ordered inside each action type.
 *
 * @property \App\Model\Table\PackagesTable&\Cake\ORM\Association\BelongsTo $Packages
 * @property \App\Model\Table\ExitCodesTable&\Cake\ORM\Association\HasMany $ExitCodes
 */
class PackageActionsTable extends Table
{
    /**
     * Declares associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('package_actions');
        $this->setDisplayField('command');
        $this->addBehavior('Position', ['scope' => ['package_id', 'type']]);

        $this->belongsTo('Packages', ['foreignKey' => 'package_id']);
        $this->hasMany('ExitCodes', [
            'foreignKey' => 'package_action_id',
            'dependent' => true,
            'sort' => ['ExitCodes.id' => 'ASC'],
        ]);
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
            ->requirePresence('command', 'create')
            ->notEmptyString('command', 'The package action command attribute is invalid.')
            ->maxLength('command', 500)
            ->requirePresence('type', 'create')
            ->add('type', 'validRange', [
                'rule' => fn(mixed $type): bool => ActionType::tryFrom((int)$type) !== null,
                'message' => 'The package action type attribute is invalid.',
            ])
            ->allowEmptyString('timeout')
            ->integer('timeout', 'The package action timeout attribute is invalid.')
            ->allowEmptyString('workdir')
            ->maxLength('workdir', 500);
    }

    /**
     * Declares integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['package_id'], 'Packages'));

        return $rules;
    }
}
