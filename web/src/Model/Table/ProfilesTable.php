<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\VariableType;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Profiles: groups of packages, with dependencies on other profiles.
 *
 * @property \App\Model\Table\PackagesTable&\Cake\ORM\Association\BelongsToMany $Packages
 * @property \App\Model\Table\ProfilesTable&\Cake\ORM\Association\BelongsToMany $ProfileDependencies
 * @property \App\Model\Table\HostsTable&\Cake\ORM\Association\HasMany $MainHosts
 * @property \App\Model\Table\HostsTable&\Cake\ORM\Association\BelongsToMany $Hosts
 * @property \App\Model\Table\VariablesTable&\Cake\ORM\Association\HasMany $Variables
 */
class ProfilesTable extends Table
{
    /**
     * Identifier shared by packages and profiles: starts with a letter or digit.
     */
    public const string ID_TEXT_PATTERN =
        '/^[\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}][\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}_\-]*$/u';

    /**
     * Declares associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('profiles');
        $this->setDisplayField('id_text');
        $this->addBehavior('Timestamp');

        $this->belongsToMany('Packages', [
            'joinTable' => 'packages_profiles',
            'foreignKey' => 'profile_id',
            'targetForeignKey' => 'package_id',
            'sort' => ['Packages.name' => 'ASC'],
        ]);
        $this->belongsToMany('ProfileDependencies', [
            'className' => 'Profiles',
            'joinTable' => 'profiles_profiles',
            'foreignKey' => 'profile_id',
            'targetForeignKey' => 'dependency_id',
            'sort' => ['ProfileDependencies.id_text' => 'ASC'],
        ]);
        $this->hasMany('MainHosts', [
            'className' => 'Hosts',
            'foreignKey' => 'mainprofile_id',
            'sort' => ['MainHosts.name' => 'ASC'],
        ]);
        $this->belongsToMany('Hosts', [
            'joinTable' => 'hosts_profiles',
            'foreignKey' => 'profile_id',
            'targetForeignKey' => 'host_id',
        ]);
        $this->hasMany('Variables', [
            'foreignKey' => 'ref_id',
            'conditions' => ['ref_type' => VariableType::Profile->value],
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
            ->requirePresence('id_text', 'create')
            ->notEmptyString('id_text')
            ->maxLength('id_text', 100)
            ->regex(
                'id_text',
                self::ID_TEXT_PATTERN,
                "The profile's id must start with a letter or number and only contain: "
                . 'letters, numbers, underscores, and hyphens.',
            );
    }

    /**
     * Declares integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['id_text'], 'That profile already exists.'));

        return $rules;
    }

    /**
     * Selects what profiles.xml exposes.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param bool $includeDisabled Keep disabled profiles.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForFeed(SelectQuery $query, bool $includeDisabled = false): SelectQuery
    {
        if (!$includeDisabled) {
            $query->where(['Profiles.enabled' => true]);
        }

        return $query
            ->contain([
                'Variables',
                'ProfileDependencies',
                'Packages' => fn(SelectQuery $packages): SelectQuery => $packages->where(['Packages.enabled' => true]),
            ])
            ->orderBy(['Profiles.id_text' => 'ASC']);
    }

    /**
     * Lists enabled profiles as id => id_text.
     *
     * @param array<int> $excludedIds Profiles to leave out.
     * @return array<int, string>
     */
    public function enabledList(array $excludedIds = []): array
    {
        $alias = $this->getAlias();
        $query = $this->find('list', valueField: 'id_text')
            ->where([$alias . '.enabled' => true])
            ->orderBy([$alias . '.id_text' => 'ASC']);
        if ($excludedIds) {
            $query->where([$alias . '.id NOT IN' => $excludedIds]);
        }

        return $query->toArray();
    }
}
