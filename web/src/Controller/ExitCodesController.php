<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Manages the exit codes of a package action.
 *
 * @property \App\Model\Table\ExitCodesTable $ExitCodes
 */
class ExitCodesController extends AppController
{
    /**
     * Adds an exit code to an action.
     *
     * @param int $actionId Action id.
     * @return \Cake\Http\Response|null
     */
    public function add(int $actionId): ?Response
    {
        $action = $this->ExitCodes->PackageActions->get($actionId);
        $exitCode = $this->ExitCodes->newEmptyEntity();
        if ($this->request->is('post')) {
            $exitCode = $this->ExitCodes->patchEntity($exitCode, $this->request->getData());
            $exitCode->package_action_id = $actionId;
            if ($this->ExitCodes->save($exitCode)) {
                $this->Flash->success(__('The Exit Code has been saved.'));

                return $this->redirect(['controller' => 'PackageActions', 'action' => 'view', $actionId]);
            }
            $this->Flash->error(__('The Exit Code could not be saved. Please, try again.'));
        }
        $this->set(compact('action', 'exitCode'));

        return null;
    }

    /**
     * Edits an exit code.
     *
     * @param int $id Exit code id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $exitCode = $this->ExitCodes->get($id, contain: ['PackageActions']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $exitCode = $this->ExitCodes->patchEntity($exitCode, $this->request->getData(), ['fields' => [
                'code', 'reboot',
            ]]);
            if ($this->ExitCodes->save($exitCode)) {
                $this->Flash->success(__('The Exit Code has been saved.'));

                return $this->redirect([
                    'controller' => 'PackageActions',
                    'action' => 'view',
                    $exitCode->package_action_id,
                ]);
            }
            $this->Flash->error(__('The Exit Code could not be saved. Please, try again.'));
        }
        $this->set('exitCode', $exitCode);
        $this->set('action', $exitCode->package_action);

        return null;
    }

    /**
     * Deletes an exit code.
     *
     * @param int $id Exit code id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $exitCode = $this->ExitCodes->get($id);
        if ($this->ExitCodes->delete($exitCode)) {
            $this->Flash->success(__('Package Exitcode deleted.'));
        } else {
            $this->Flash->error(__('An error occurred while attempting to delete that Package Exitcode.'));
        }

        return $this->redirect(['controller' => 'PackageActions', 'action' => 'view', $exitCode->package_action_id]);
    }
}
