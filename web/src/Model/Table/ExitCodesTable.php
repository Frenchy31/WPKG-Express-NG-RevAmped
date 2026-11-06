<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\ExitCodeReboot;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Exit codes accepted by a package action.
 *
 * @property \App\Model\Table\PackageActionsTable&\Cake\ORM\Association\BelongsTo $PackageActions
 */
class ExitCodesTable extends Table
{
    /**
     * Declares associations.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('exit_codes');
        $this->setDisplayField('code');
        $this->belongsTo('PackageActions', ['foreignKey' => 'package_action_id']);
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
            ->requirePresence('code', 'create')
            ->notEmptyString('code')
            ->add('code', 'validCode', [
                'rule' => fn(mixed $code): bool => self::isValidCode((string)$code),
                'message' => "The exit code's code attribute must be an integer or the string, 'any'.",
            ])
            ->add('reboot', 'validRange', [
                'rule' => fn(mixed $reboot): bool => ExitCodeReboot::tryFrom((int)$reboot) !== null,
                'message' => "The exit code's reboot attribute is invalid.",
            ]);
    }

    /**
     * Declares integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['package_action_id'], 'PackageActions'));
        $rules->add($rules->isUnique(['package_action_id', 'code']), 'uniqueCode', [
            'errorField' => 'code',
            'message' => "The exit code's code already exists for this package action.",
        ]);

        return $rules;
    }

    /**
     * Tells whether a value is an integer exit code or the keyword `any`.
     *
     * @param string $code Exit code.
     * @return bool
     */
    public static function isValidCode(string $code): bool
    {
        return strtolower($code) === 'any' || (string)(int)$code === $code;
    }
}
