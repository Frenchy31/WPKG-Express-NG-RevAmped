<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Variable;
use App\Model\Enum\VariableType;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Manages the variables of packages, profiles and hosts.
 *
 * @property \App\Model\Table\VariablesTable $Variables
 */
class VariablesController extends AppController
{
    /**
     * Lists the variables of a record.
     *
     * @param string $type Owner type: package, profile or host.
     * @param int $recordId Owner id.
     * @return void
     */
    public function view(string $type, int $recordId): void
    {
        $variableType = $this->resolveType($type);
        $this->setOwner($variableType, $recordId);
        $this->set('variables', $this->Variables->forRecord($variableType, $recordId));
    }

    /**
     * Adds a variable to a record.
     *
     * @param string $type Owner type: package, profile or host.
     * @param int $recordId Owner id.
     * @return \Cake\Http\Response|null
     */
    public function add(string $type, int $recordId): ?Response
    {
        $variableType = $this->resolveType($type);
        $this->setOwner($variableType, $recordId);

        $variable = $this->Variables->newEmptyEntity();
        if ($this->request->is('post')) {
            $variable = $this->Variables->patchEntity($variable, $this->request->getData());
            $variable->ref_type = $variableType->value;
            $variable->ref_id = $recordId;
            if ($this->Variables->save($variable)) {
                $this->Flash->success('The Variable has been saved');

                return $this->redirect(['action' => 'view', $type, $recordId]);
            }
            $this->Flash->error('The Variable could not be saved');
        }
        $this->set(compact('variable'));

        return null;
    }

    /**
     * Edits a variable.
     *
     * @param int $id Variable id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $variable = $this->Variables->get($id);
        $variableType = VariableType::from($variable->ref_type);
        $this->setOwner($variableType, $variable->ref_id);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $variable = $this->Variables->patchEntity($variable, $this->request->getData());
            if ($this->Variables->save($variable)) {
                $this->Flash->success('The Variable has been saved');

                return $this->redirect(['action' => 'view', strtolower($variableType->name), $variable->ref_id]);
            }
            $this->Flash->error('The Variable could not be saved');
        }
        $this->set(compact('variable'));

        return null;
    }

    /**
     * Deletes a variable.
     *
     * @param int $id Variable id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $variable = $this->Variables->get($id);
        if ($this->Variables->delete($variable)) {
            $this->Flash->success('Variable deleted');
        } else {
            $this->Flash->error('The Variable could not be deleted');
        }

        return $this->redirect(['action' => 'view', $this->slugOf($variable), $variable->ref_id]);
    }

    /**
     * Converts a URL segment into a variable type.
     *
     * @param string $type URL segment.
     * @return \App\Model\Enum\VariableType
     * @throws \Cake\Http\Exception\NotFoundException
     */
    private function resolveType(string $type): VariableType
    {
        return VariableType::fromSlug($type) ?? throw new NotFoundException('Invalid type.');
    }

    /**
     * Exposes the record owning the variables to the view.
     *
     * @param \App\Model\Enum\VariableType $variableType Owner type.
     * @param int $recordId Owner id.
     * @return void
     */
    private function setOwner(VariableType $variableType, int $recordId): void
    {
        $table = $this->fetchTable($variableType->tableName());

        $this->set('owner', $table->get($recordId));
        $this->set('ownerDisplayField', $table->getDisplayField());
        $this->set('variableType', $variableType);
    }

    /**
     * Returns the URL segment of a variable owner.
     *
     * @param \App\Model\Entity\Variable $variable Variable.
     * @return string
     */
    private function slugOf(Variable $variable): string
    {
        return strtolower(VariableType::from($variable->ref_type)->name);
    }
}
