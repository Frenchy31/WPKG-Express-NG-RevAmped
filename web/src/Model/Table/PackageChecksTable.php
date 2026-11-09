<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\PackageCheck;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Checks of a package, stored as a nested set.
 *
 * Legacy rows share one global nested set across packages. Every write therefore scopes
 * the tree to one package, which only relies on the relative order inside that package.
 *
 * @property \App\Model\Table\PackagesTable&\Cake\ORM\Association\BelongsTo $Packages
 */
class PackageChecksTable extends Table
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

        $this->setTable('package_checks');
        $this->addBehavior('Tree');

        $this->belongsTo('Packages', ['foreignKey' => 'package_id']);
        $this->belongsTo('ParentChecks', ['className' => 'PackageChecks', 'foreignKey' => 'parent_id']);
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
            ->requirePresence('type', 'create')
            ->add('type', 'validRange', [
                'rule' => fn(mixed $type): bool => CheckType::tryFrom((int)$type) !== null,
                'message' => __('The package check type attribute is invalid.'),
            ])
            ->requirePresence('condition', 'create')
            ->add('condition', 'validRange', [
                'rule' => function (mixed $condition, array $context): bool {
                    $checkCondition = CheckCondition::tryFrom((int)$condition);

                    return $checkCondition !== null
                        && $checkCondition->type()->value === (int)($context['data']['type'] ?? -1);
                },
                'message' => __('The package check condition attribute is invalid.'),
            ])
            ->allowEmptyString('path')
            ->maxLength('path', 200)
            // wpkg.js throws on an atleast or atmost check without a count.
            ->allowEmptyString(
                'value',
                __('Logical atleast and atmost checks need a count.'),
                fn(array $context): bool => !self::isCountedCondition($context['data']['condition'] ?? null),
            )
            ->add('value', 'count', [
                'rule' => function (mixed $value, array $context): bool {
                    return !self::isCountedCondition($context['data']['condition'] ?? null)
                        || ctype_digit((string)$value);
                },
                'message' => __('Logical atleast and atmost checks need a count.'),
            ])
            ->maxLength('value', 200)
            ->allowEmptyString('parent_id');
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

    /**
     * Tells whether a condition compares the number of passing child checks with a count.
     *
     * @param mixed $condition Value of the condition field.
     * @return bool
     */
    private static function isCountedCondition(mixed $condition): bool
    {
        return in_array(
            CheckCondition::tryFrom((int)$condition),
            [CheckCondition::LogicalAtLeast, CheckCondition::LogicalAtMost],
            true,
        );
    }

    /**
     * Saves a check inside the tree of its package.
     *
     * @param \App\Model\Entity\PackageCheck $check Check.
     * @return bool
     */
    public function saveInPackage(PackageCheck $check): bool
    {
        $this->scopeToPackage((int)$check->get('package_id'));

        return (bool)$this->save($check);
    }

    /**
     * Deletes a check and its descendants.
     *
     * @param \App\Model\Entity\PackageCheck $check Check.
     * @return bool
     */
    public function deleteInPackage(PackageCheck $check): bool
    {
        $this->scopeToPackage((int)$check->get('package_id'));

        return $this->delete($check);
    }

    /**
     * Moves a check among its siblings.
     *
     * @param \App\Model\Entity\PackageCheck $check Check.
     * @param string $direction One of top, up, down, bottom.
     * @return void
     */
    public function moveInPackage(PackageCheck $check, string $direction): void
    {
        $this->scopeToPackage((int)$check->get('package_id'));

        $tree = $this->getBehavior('Tree');
        match ($direction) {
            'top' => $tree->moveUp($check, true),
            'up' => $tree->moveUp($check),
            'down' => $tree->moveDown($check),
            'bottom' => $tree->moveDown($check, true),
        };
    }

    /**
     * Returns the checks of a package as a nested list.
     *
     * @param int $packageId Package id.
     * @return array<\App\Model\Entity\PackageCheck>
     */
    public function threadedForPackage(int $packageId): array
    {
        return $this->find('threaded')
            ->where(['package_id' => $packageId])
            ->orderBy(['lft' => 'ASC'])
            ->toArray();
    }

    /**
     * Lists the logical checks of a package, usable as parents, as id => indented label.
     *
     * @param int $packageId Package id.
     * @return array<int, string>
     */
    public function logicalParentsList(int $packageId): array
    {
        $checks = $this->find()
            ->where(['package_id' => $packageId, 'type' => CheckType::Logical->value])
            ->orderBy(['lft' => 'ASC'])
            ->all();

        $options = [0 => __('Root Node')];
        $openRights = [];
        foreach ($checks as $check) {
            while ($openRights && end($openRights) < $check->get('lft')) {
                array_pop($openRights);
            }
            $condition = CheckCondition::tryFrom($check->get('condition'));
            $options[$check->get('id')] = str_repeat('-', count($openRights) + 1)
                . __('Logical {0}', ucfirst($condition?->xmlValue() ?? 'unknown'));
            $openRights[] = $check->get('rght');
        }

        return $options;
    }

    /**
     * Restricts tree operations to one package.
     *
     * @param int $packageId Package id.
     * @return void
     */
    protected function scopeToPackage(int $packageId): void
    {
        $this->getBehavior('Tree')->setConfig('scope', ['package_id' => $packageId], false);
    }
}
