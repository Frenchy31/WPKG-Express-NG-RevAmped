<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Manages the commands of a package.
 *
 * @property \App\Model\Table\PackageActionsTable $PackageActions
 */
class PackageActionsController extends AppController
{
    /**
     * Shows an action and its exit codes.
     *
     * @param int $id Action id.
     * @return void
     */
    public function view(int $id): void
    {
        $this->set('action', $this->PackageActions->get($id, contain: ['Packages', 'ExitCodes']));
    }

    /**
     * Adds an action to a package.
     *
     * @param int $packageId Package id.
     * @return \Cake\Http\Response|null
     */
    public function add(int $packageId): ?Response
    {
        $package = $this->PackageActions->Packages->get($packageId);
        $action = $this->PackageActions->newEmptyEntity();
        if ($this->request->is('post')) {
            $action = $this->PackageActions->patchEntity($action, $this->request->getData());
            $action->package_id = $packageId;
            if ($this->PackageActions->save($action)) {
                $this->Flash->success('The Package Action has been saved.');

                return $this->redirect(['controller' => 'Packages', 'action' => 'view', $packageId]);
            }
            $this->Flash->error('The Package Action could not be saved. Please, try again.');
        }
        $this->set(compact('action', 'package'));

        return null;
    }

    /**
     * Edits an action.
     *
     * @param int $id Action id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $action = $this->PackageActions->get($id, contain: ['Packages']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $action = $this->PackageActions->patchEntity($action, $this->request->getData(), ['fields' => [
                'type', 'command', 'timeout', 'workdir',
            ]]);
            if ($this->PackageActions->save($action)) {
                $this->Flash->success('The Package Action has been saved.');

                return $this->redirect(['controller' => 'Packages', 'action' => 'view', $action->package_id]);
            }
            $this->Flash->error('The Package Action could not be saved. Please, try again.');
        }
        $this->set(compact('action'));

        return null;
    }

    /**
     * Deletes an action.
     *
     * @param int $id Action id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $action = $this->PackageActions->get($id);
        if ($this->PackageActions->delete($action)) {
            $this->Flash->success('Package Action deleted.');
        } else {
            $this->Flash->error('An error occurred while attempting to delete that Package Action.');
        }

        return $this->redirect(['controller' => 'Packages', 'action' => 'view', $action->package_id]);
    }

    /**
     * Moves an action among the actions of the same type.
     *
     * @param int $id Action id.
     * @param string $direction One of top, up, down, bottom.
     * @return \Cake\Http\Response|null
     */
    public function move(int $id, string $direction): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->assertMoveDirection($direction);
        $action = $this->PackageActions->get($id);
        $this->PackageActions->getBehavior('Position')->move($action, $direction);

        return $this->redirect(['controller' => 'Packages', 'action' => 'view', $action->package_id]);
    }
}
