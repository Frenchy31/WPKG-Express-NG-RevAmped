<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Manages packages.
 *
 * @property \App\Model\Table\PackagesTable $Packages
 */
class PackagesController extends AppController
{
    /**
     * Lists packages by decreasing priority.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('packages', $this->paginate($this->Packages->find(), [
            'limit' => 25,
            'order' => ['Packages.priority' => 'DESC', 'Packages.name' => 'ASC'],
            'sortableFields' => [
                'enabled', 'name', 'id_text', 'revision', 'priority', 'reboot', 'execute', 'notify', 'modified',
            ],
        ]));
    }

    /**
     * Shows a package with its checks and actions.
     *
     * @param int $id Package id.
     * @return void
     */
    public function view(int $id): void
    {
        $this->set('package', $this->Packages->get($id, contain: [
            'PackageDependencies', 'DependedOnBy', 'Profiles', 'PackageActions',
        ]));
        $this->set('checks', $this->Packages->PackageChecks->threadedForPackage($id));
    }

    /**
     * Adds a package.
     *
     * @return \Cake\Http\Response|null
     */
    public function add(): ?Response
    {
        $package = $this->Packages->newEmptyEntity();
        if ($this->request->is('post')) {
            $package = $this->Packages->patchEntity($package, $this->request->getData(), ['associated' => []]);
            if ($this->Packages->save($package)) {
                $this->Flash->success(__('The Package has been saved.'));

                return $this->redirect(['action' => 'view', $package->id]);
            }
            $this->Flash->error(__('The Package could not be saved. Please, try again.'));
        }
        $this->set(compact('package'));

        return null;
    }

    /**
     * Edits a package and its dependencies.
     *
     * @param int $id Package id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $package = $this->Packages->get($id, contain: ['PackageDependencies']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $package = $this->Packages->patchEntity($package, $this->request->getData(), [
                'associated' => ['PackageDependencies' => ['onlyIds' => true]],
            ]);
            if ($this->Packages->save($package)) {
                $this->Flash->success(__('The Package has been saved.'));

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('The Package could not be saved. Please, try again.'));
        }

        $this->set('package', $package);
        $this->set('packageDependencies', $this->Packages->find('list')
            ->where(['Packages.id <>' => $id])
            ->orderBy(['Packages.name' => 'ASC'])
            ->toArray());

        return null;
    }

    /**
     * Deletes a package nothing depends on.
     *
     * @param int $id Package id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $dependents = $this->dependentNames($id);
        if ($dependents !== '') {
            $this->Flash->error(
                __('An error occurred while attempting to delete that Package. It is depended on by: {0}', $dependents),
            );
        } elseif ($this->Packages->delete($this->Packages->get($id))) {
            $this->Flash->success(__('Package deleted.'));
        } else {
            $this->Flash->error(__('An error occurred while attempting to delete that Package.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Enables a package.
     *
     * @param int $id Package id.
     * @return \Cake\Http\Response|null
     */
    public function enable(int $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $package = $this->Packages->get($id);
        $package->enabled = true;
        $this->Packages->saveOrFail($package);

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Disables a package nothing depends on.
     *
     * @param int $id Package id.
     * @return \Cake\Http\Response|null
     */
    public function disable(int $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $package = $this->Packages->get($id);
        $dependents = $this->dependentNames($id);
        if ($dependents !== '') {
            $this->Flash->error(__(
                'The Package \'\'{0}\'\' cannot be disabled because it is depended on by the following package(s): {1}',
                $package->name,
                $dependents,
            ));
        } else {
            $package->enabled = false;
            $this->Packages->saveOrFail($package);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Lists the identifiers of the packages depending on a package.
     *
     * @param int $id Package id.
     * @return string Comma separated identifiers, empty when there are none.
     */
    private function dependentNames(int $id): string
    {
        return implode(', ', array_map(fn($package): string => $package->id_text, $this->Packages->dependents($id)));
    }
}
