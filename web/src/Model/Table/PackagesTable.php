<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Enum\PackageExecute;
use App\Model\Enum\PackageReboot;
use App\Model\Enum\VariableType;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Packages: software definitions consumed by clients through packages.xml.
 *
 * @property \App\Model\Table\PackageActionsTable&\Cake\ORM\Association\HasMany $PackageActions
 * @property \App\Model\Table\PackageChecksTable&\Cake\ORM\Association\HasMany $PackageChecks
 * @property \App\Model\Table\VariablesTable&\Cake\ORM\Association\HasMany $Variables
 * @property \App\Model\Table\PackagesTable&\Cake\ORM\Association\BelongsToMany $PackageDependencies
 * @property \App\Model\Table\PackagesTable&\Cake\ORM\Association\BelongsToMany $DependedOnBy
 * @property \App\Model\Table\ProfilesTable&\Cake\ORM\Association\BelongsToMany $Profiles
 */
class PackagesTable extends Table
{
    /**
     * Revision: dotted digits, or anything containing a %VARIABLE%.
     */
    public const string REVISION_PATTERN = '/^(\d+(\.\d+)*|.*%[^%]+%.*)$/';

    /**
     * Declares associations and behaviors.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('packages');
        $this->setDisplayField('name');
        $this->addBehavior('Timestamp');

        $this->hasMany('PackageActions', [
            'foreignKey' => 'package_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['PackageActions.type' => 'ASC', 'PackageActions.position' => 'ASC'],
        ]);
        $this->hasMany('PackageChecks', [
            'foreignKey' => 'package_id',
            'dependent' => true,
            'sort' => ['PackageChecks.lft' => 'ASC'],
        ]);
        $this->hasMany('Variables', [
            'foreignKey' => 'ref_id',
            'conditions' => ['ref_type' => VariableType::Package->value],
            'dependent' => true,
            'sort' => ['Variables.id' => 'ASC'],
        ]);
        $this->belongsToMany('PackageDependencies', [
            'className' => 'Packages',
            'joinTable' => 'packages_packages',
            'foreignKey' => 'package_id',
            'targetForeignKey' => 'dependency_id',
            'sort' => ['PackageDependencies.name' => 'ASC'],
        ]);
        $this->belongsToMany('DependedOnBy', [
            'className' => 'Packages',
            'joinTable' => 'packages_packages',
            'foreignKey' => 'dependency_id',
            'targetForeignKey' => 'package_id',
            'propertyName' => 'depended_on_by',
            'sort' => ['DependedOnBy.name' => 'ASC'],
        ]);
        $this->belongsToMany('Profiles', [
            'joinTable' => 'packages_profiles',
            'foreignKey' => 'package_id',
            'targetForeignKey' => 'profile_id',
            'sort' => ['Profiles.id_text' => 'ASC'],
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
            ->notEmptyString('name')
            ->maxLength('name', 100)
            ->requirePresence('id_text', 'create')
            ->notEmptyString('id_text')
            ->maxLength('id_text', 100)
            ->regex(
                'id_text',
                ProfilesTable::ID_TEXT_PATTERN,
                __(
                    "The package's id must start with a letter or number and only contain: "
                    . 'letters, numbers, underscores, and hyphens.',
                ),
            )
            ->maxLength('revision', 35)
            ->regex(
                'revision',
                self::REVISION_PATTERN,
                __(
                    "The package's revision attribute is not formatted properly. It must start with a digit. "
                    . 'If it contains periods, you must have at least one digit after each.',
                ),
            )
            ->integer('priority', __("The package's priority attribute is not an integer."))
            ->add('reboot', 'validRange', [
                'rule' => fn(mixed $reboot): bool => PackageReboot::tryFrom((int)$reboot) !== null,
                'message' => __("The package's reboot attribute is invalid."),
            ])
            ->add('execute', 'validRange', [
                'rule' => fn(mixed $execute): bool => PackageExecute::tryFrom((int)$execute) !== null,
                'message' => __("The package's execute attribute is invalid."),
            ])
            ->boolean('notify', __("The package's notify attribute must be true or false."));
    }

    /**
     * Declares integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['id_text'], __('That package already exists.')));

        return $rules;
    }

    /**
     * Applies the defaults of blank optional fields.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event Event.
     * @param \ArrayObject<string, mixed> $values Request values.
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $values): void
    {
        foreach (['revision' => '0', 'priority' => 0] as $field => $default) {
            if (isset($values[$field]) && trim((string)$values[$field]) === '') {
                $values[$field] = $default;
            }
        }
    }

    /**
     * Selects what packages.xml exposes.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query.
     * @param bool $includeDisabled Keep disabled packages.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForFeed(SelectQuery $query, bool $includeDisabled = false): SelectQuery
    {
        if (!$includeDisabled) {
            $query->where(['Packages.enabled' => true]);
        }

        return $query
            ->contain(['Variables', 'PackageDependencies', 'PackageChecks', 'PackageActions.ExitCodes'])
            ->orderBy(['Packages.priority' => 'DESC', 'Packages.name' => 'ASC']);
    }

    /**
     * Returns the packages depending on a package.
     *
     * @param int $packageId Package id.
     * @return array<\App\Model\Entity\Package>
     */
    public function dependents(int $packageId): array
    {
        return $this->find()
            ->matching(
                'PackageDependencies',
                fn(SelectQuery $dependencies): SelectQuery => $dependencies
                    ->where(['PackageDependencies.id' => $packageId]),
            )
            ->orderBy(['Packages.id_text' => 'ASC'])
            ->all()
            ->toList();
    }

    /**
     * Copies the associations pointing at a replaced package onto its successor.
     *
     * @param int $previousId Replaced package id.
     * @param int $currentId New package id.
     * @return void
     */
    public function transferAssociations(int $previousId, int $currentId): void
    {
        $this->Profiles->junction()->updateAll(['package_id' => $currentId], ['package_id' => $previousId]);
        $this->DependedOnBy->junction()->updateAll(['dependency_id' => $currentId], ['dependency_id' => $previousId]);
    }

    /**
     * Deletes a package together with its children, without touching the rows of its associations.
     *
     * @param \Cake\Datasource\EntityInterface $package Package.
     * @return bool
     */
    public function deleteKeepingAssociations(EntityInterface $package): bool
    {
        $this->PackageActions->deleteMany($this->PackageActions->find()->where(['package_id' => $package->get('id')]));
        $this->PackageChecks->deleteAll(['package_id' => $package->get('id')]);
        $this->Variables->deleteAll(['ref_id' => $package->get('id'), 'ref_type' => VariableType::Package->value]);
        $this->PackageDependencies->junction()->deleteAll(['package_id' => $package->get('id')]);

        return (bool)$this->deleteAll(['id' => $package->get('id')]);
    }
}
