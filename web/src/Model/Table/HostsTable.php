<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\VariableType;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Hosts: name patterns matched by clients, each with a main profile.
 *
 * @property \App\Model\Table\ProfilesTable&\Cake\ORM\Association\BelongsTo $MainProfiles
 * @property \App\Model\Table\ProfilesTable&\Cake\ORM\Association\BelongsToMany $Profiles
 * @property \App\Model\Table\VariablesTable&\Cake\ORM\Association\HasMany $Variables
 */
class HostsTable extends Table
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

        $this->setTable('hosts');
        $this->setDisplayField('name');
        $this->addBehavior('Timestamp');
        $this->addBehavior('Position');

        $this->belongsTo('MainProfiles', [
            'className' => 'Profiles',
            'foreignKey' => 'mainprofile_id',
            'propertyName' => 'main_profile',
        ]);
        $this->belongsToMany('Profiles', [
            'joinTable' => 'hosts_profiles',
            'foreignKey' => 'host_id',
            'targetForeignKey' => 'profile_id',
            'sort' => ['Profiles.id_text' => 'ASC'],
        ]);
        $this->hasMany('Variables', [
            'foreignKey' => 'ref_id',
            'conditions' => ['ref_type' => VariableType::Host->value],
            'dependent' => true,
            'sort' => ['Variables.id' => 'ASC'],
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
            ->requirePresence('name', 'create')
            ->notEmptyString('name', "The host's name is an invalid regular expression.")
            ->maxLength('name', 100)
            ->add('name', 'validRegex', [
                'rule' => fn(mixed $name): bool => self::isValidPattern((string)$name),
                'message' => "The host's name is an invalid regular expression.",
            ])
            ->requirePresence('mainprofile_id', 'create')
            ->integer('mainprofile_id', "The host's main profile could not be found.");
    }

    /**
     * Declares integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name'], 'That host already exists.'));
        $rules->add($rules->existsIn(
            ['mainprofile_id'],
            'MainProfiles',
            "The host's main profile could not be found.",
        ));

        return $rules;
    }

    /**
     * Selects what hosts.xml exposes.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param bool $includeDisabled Keep disabled hosts.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForFeed(SelectQuery $query, bool $includeDisabled = false): SelectQuery
    {
        if (!$includeDisabled) {
            $query->where(['Hosts.enabled' => true]);
        }

        return $query
            ->contain([
                'MainProfiles',
                'Variables',
                'Profiles' => fn(SelectQuery $profiles): SelectQuery => $profiles->where(['Profiles.enabled' => true]),
            ])
            ->orderBy(['Hosts.position' => 'ASC', 'Hosts.id' => 'ASC']);
    }

    /**
     * Tells whether a host name compiles as a regular expression.
     *
     * @param string $pattern Host name.
     * @return bool
     */
    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match('/' . str_replace('/', '\/', $pattern) . '/', '') !== false;
    }
}
