<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Manages hosts.
 *
 * @property \App\Model\Table\HostsTable $Hosts
 */
class HostsController extends AppController
{
    /**
     * Lists hosts in matching order.
     *
     * @return void
     */
    public function index(): void
    {
        $query = $this->Hosts->find()->contain(['MainProfiles']);

        $this->set('hosts', $this->paginate($query, [
            'limit' => 25,
            'order' => ['Hosts.position' => 'ASC'],
            'sortableFields' => ['enabled', 'position', 'name', 'MainProfiles.id_text', 'modified'],
        ]));
    }

    /**
     * Shows a host.
     *
     * @param int $id Host id.
     * @return void
     */
    public function view(int $id): void
    {
        $this->set('host', $this->Hosts->get($id, contain: ['MainProfiles', 'Profiles']));
    }

    /**
     * Adds a host.
     *
     * @return \Cake\Http\Response|null
     */
    public function add(): ?Response
    {
        if ($this->Hosts->MainProfiles->find()->count() === 0) {
            $this->Flash->error(__('You must add at least one Profile first'));

            return $this->redirect(['action' => 'index']);
        }

        $host = $this->Hosts->newEmptyEntity();
        if ($this->request->is('post')) {
            $host = $this->Hosts->patchEntity($host, $this->request->getData());
            if ($this->Hosts->save($host)) {
                $this->Flash->success(__('The Host has been saved'));

                return $this->redirect(['action' => 'view', $host->id]);
            }
            $this->Flash->error(__('The Host could not be saved. Please, try again.'));
        }

        $this->set('host', $host);
        $this->set('profiles', $this->Hosts->MainProfiles->enabledList());

        return null;
    }

    /**
     * Edits a host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $host = $this->Hosts->get($id, contain: ['Profiles']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $host = $this->Hosts->patchEntity($host, $this->request->getData(), ['associated' => []]);
            if ($this->Hosts->save($host)) {
                $this->Flash->success(__('The Host has been saved'));

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('The Host could not be saved. Please, try again.'));
        }

        $extraProfileIds = array_map(fn($profile): int => $profile->id, $host->profiles);
        $this->set('host', $host);
        $this->set('profiles', $this->Hosts->MainProfiles->enabledList($extraProfileIds));

        return null;
    }

    /**
     * Chooses the additional profiles of a host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response|null
     */
    public function profiles(int $id): ?Response
    {
        $host = $this->Hosts->get($id, contain: ['Profiles']);
        $profiles = $this->Hosts->MainProfiles->enabledList([$host->mainprofile_id]);
        if (!$profiles) {
            $this->Flash->error(__('There are no other Profiles to add/remove'));

            return $this->redirect(['action' => 'view', $id]);
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $host = $this->Hosts->patchEntity($host, $this->request->getData(), [
                'fields' => ['profiles'],
                'associated' => ['Profiles' => ['onlyIds' => true]],
            ]);
            if ($this->Hosts->save($host)) {
                $this->Flash->success(__('The Host has been saved'));

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('The Host could not be saved. Please, try again.'));
        }

        $this->set(compact('host', 'profiles'));

        return null;
    }

    /**
     * Removes an additional profile from a host.
     *
     * @param int $id Host id.
     * @param int $profileId Profile id.
     * @return \Cake\Http\Response|null
     */
    public function removeProfile(int $id, int $profileId): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $host = $this->Hosts->get($id);
        $profile = $this->Hosts->Profiles->get($profileId);

        $this->Hosts->Profiles->unlink($host, [$profile]);
        $this->Flash->success(__('Host\'\'s association with Profile \'\'{0}\'\' deleted', $profile->id_text));

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Deletes a host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        if ($this->Hosts->delete($this->Hosts->get($id))) {
            $this->Flash->success(__('Host deleted'));
        } else {
            $this->Flash->error(__('The Host could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Moves a host in matching order.
     *
     * @param int $id Host id.
     * @param string $direction One of top, up, down, bottom.
     * @return \Cake\Http\Response|null
     */
    public function move(int $id, string $direction): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->assertMoveDirection($direction);
        $this->Hosts->getBehavior('Position')->move($this->Hosts->get($id), $direction);

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Enables a host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response|null
     */
    public function enable(int $id): ?Response
    {
        return $this->setEnabled($id, true);
    }

    /**
     * Disables a host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response|null
     */
    public function disable(int $id): ?Response
    {
        return $this->setEnabled($id, false);
    }

    /**
     * Changes the enabled flag of a host.
     *
     * @param int $id Host id.
     * @param bool $enabled New state.
     * @return \Cake\Http\Response|null
     */
    private function setEnabled(int $id, bool $enabled): ?Response
    {
        $this->request->allowMethod(['post']);
        $host = $this->Hosts->get($id);
        $host->enabled = $enabled;
        $this->Hosts->saveOrFail($host);

        return $this->redirect(['action' => 'index']);
    }
}
