<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\PackageCheck;
use App\Model\Enum\CheckCondition;
use App\Model\Enum\CheckType;
use Cake\Http\Response;

/**
 * Manages the check tree of a package.
 *
 * @property \App\Model\Table\PackageChecksTable $PackageChecks
 */
class PackageChecksController extends AppController
{
    /**
     * Adds a check to a package.
     *
     * @param int $packageId Package id.
     * @return \Cake\Http\Response|null
     */
    public function add(int $packageId): ?Response
    {
        $package = $this->PackageChecks->Packages->get($packageId);
        $check = $this->PackageChecks->newEmptyEntity();
        if ($this->request->is('post')) {
            $check = $this->PackageChecks->patchEntity($check, $this->submittedData());
            $check->package_id = $packageId;
            if ($this->PackageChecks->saveInPackage($check)) {
                $this->Flash->success(__('The Package Check has been saved.'));

                return $this->redirect(['controller' => 'Packages', 'action' => 'view', $packageId]);
            }
            $this->Flash->error(__('The Package Check could not be saved. Please, try again.'));
        }

        $this->setFormVariables($check, $packageId);
        $this->set(compact('package'));

        return null;
    }

    /**
     * Edits a check.
     *
     * @param int $id Check id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $check = $this->PackageChecks->get($id, contain: ['Packages']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $check = $this->PackageChecks->patchEntity($check, $this->submittedData());
            if ($this->PackageChecks->saveInPackage($check)) {
                $this->Flash->success(__('The Package Check has been saved.'));

                return $this->redirect(['controller' => 'Packages', 'action' => 'view', $check->package_id]);
            }
            $this->Flash->error(__('The Package Check could not be saved. Please, try again.'));
        }

        $this->setFormVariables($check, $check->package_id);
        $this->set('package', $check->package);

        return null;
    }

    /**
     * Deletes a check and its descendants.
     *
     * @param int $id Check id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $check = $this->PackageChecks->get($id);
        if ($this->PackageChecks->deleteInPackage($check)) {
            $this->Flash->success(__('Package Check deleted.'));
        } else {
            $this->Flash->error(__('An error occurred while attempting to delete that Package Check.'));
        }

        return $this->redirect(['controller' => 'Packages', 'action' => 'view', $check->package_id]);
    }

    /**
     * Moves a check among its siblings.
     *
     * @param int $id Check id.
     * @param string $direction One of top, up, down, bottom.
     * @return \Cake\Http\Response|null
     */
    public function move(int $id, string $direction): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->assertMoveDirection($direction);
        $check = $this->PackageChecks->get($id);
        $this->PackageChecks->moveInPackage($check, $direction);

        return $this->redirect(['controller' => 'Packages', 'action' => 'view', $check->package_id]);
    }

    /**
     * Returns the submitted fields, with the root node of the parent list turned into no parent.
     *
     * @return array<string, mixed>
     */
    private function submittedData(): array
    {
        $submitted = $this->request->getData();
        if (array_key_exists('parent_id', $submitted) && empty($submitted['parent_id'])) {
            $submitted['parent_id'] = null;
        }

        return $submitted;
    }

    /**
     * Prepares the form. The `type` and `cond` query parameters select the fields to show, then
     * the values of the check, which also keeps the choices of a submission that failed validation.
     *
     * @param \App\Model\Entity\PackageCheck $check Check.
     * @param int $packageId Package id.
     * @return void
     */
    private function setFormVariables(PackageCheck $check, int $packageId): void
    {
        $currentType = CheckType::tryFrom((int)($check->type ?? CheckType::Uninstall->value)) ?? CheckType::Uninstall;
        $type = CheckType::tryFrom((int)$this->request->getQuery('type', $currentType->value)) ?? $currentType;
        $conditions = $type->conditions();

        $condition = CheckCondition::tryFrom((int)$this->request->getQuery('cond', $check->condition ?? -1));
        if ($condition === null || $condition->type() !== $type) {
            $condition = $conditions[0];
        }

        $conditionOptions = [];
        foreach ($conditions as $option) {
            $conditionOptions[$option->value] = $option->xmlValue();
        }

        // A logical check keeps its place in the tree: its type and parent are not editable.
        $isExistingLogicalCheck = !$check->isNew() && $check->type === CheckType::Logical->value;
        $this->set('parents', $isExistingLogicalCheck ? null : $this->PackageChecks->logicalParentsList($packageId));
        $this->set(compact('check', 'type', 'condition', 'conditionOptions'));
    }
}
