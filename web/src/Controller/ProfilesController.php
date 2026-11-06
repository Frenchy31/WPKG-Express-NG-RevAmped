<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Manages profiles.
 *
 * @property \App\Model\Table\ProfilesTable $Profiles
 */
class ProfilesController extends AppController
{
    /**
     * Lists profiles.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('profiles', $this->paginate($this->Profiles->find(), [
            'limit' => 25,
            'order' => ['Profiles.id_text' => 'ASC'],
            'sortableFields' => ['enabled', 'id_text', 'modified'],
        ]));
    }

    /**
     * Shows a profile.
     *
     * @param int $id Profile id.
     * @return void
     */
    public function view(int $id): void
    {
        $this->set('profile', $this->Profiles->get($id, contain: ['ProfileDependencies', 'Packages']));
    }

    /**
     * Lists the hosts attached to a profile, as main or additional profile, in matching order.
     *
     * @param int $id Profile id.
     * @return void
     */
    public function hosts(int $id): void
    {
        $this->request->allowMethod(['get']);
        $profile = $this->Profiles->get($id, contain: ['MainHosts', 'Hosts']);

        $attachedHosts = [];
        foreach ($profile->hosts as $host) {
            $attachedHosts[$host->id] = ['host' => $host, 'link' => 'Additional'];
        }
        foreach ($profile->main_hosts as $host) {
            $attachedHosts[$host->id] = ['host' => $host, 'link' => 'Main'];
        }
        uasort($attachedHosts, fn(array $left, array $right): int => [$left['host']->position, $left['host']->id]
            <=> [$right['host']->position, $right['host']->id]);

        $this->set(compact('profile', 'attachedHosts'));
    }

    /**
     * Adds a profile.
     *
     * @return \Cake\Http\Response|null
     */
    public function add(): ?Response
    {
        $profile = $this->Profiles->newEmptyEntity();
        if ($this->request->is('post')) {
            $profile = $this->Profiles->patchEntity($profile, $this->request->getData(), ['associated' => []]);
            if ($this->Profiles->save($profile)) {
                $this->Flash->success('The Profile has been saved.');

                return $this->redirect(['action' => 'view', $profile->id]);
            }
            $this->Flash->error('The Profile could not be saved. Please, try again.');
        }
        $this->set(compact('profile'));

        return null;
    }

    /**
     * Edits a profile. A main profile stays enabled.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id): ?Response
    {
        $profile = $this->Profiles->get($id, contain: ['ProfileDependencies']);
        if ($this->request->is(['post', 'put', 'patch'])) {
            $submitted = $this->request->getData();
            if ($profile->enabled && empty($submitted['enabled'])) {
                $blocker = $this->disableBlocker($profile->id);
                if ($blocker !== null) {
                    $this->Flash->warning($blocker);
                    unset($submitted['enabled']);
                }
            }

            $profile = $this->Profiles->patchEntity($profile, $submitted, [
                'associated' => ['ProfileDependencies' => ['onlyIds' => true]],
            ]);
            if ($this->Profiles->save($profile)) {
                $this->Flash->success('The Profile has been saved.');

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('The Profile could not be saved. Please, try again.');
        }

        $this->set('profile', $profile);
        $this->set('profileDependencies', $this->Profiles->enabledList([$id]));

        return null;
    }

    /**
     * Chooses the packages of a profile.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response|null
     */
    public function packages(int $id): ?Response
    {
        $profile = $this->Profiles->get($id, contain: ['Packages']);
        $packages = [];
        foreach ($this->Profiles->Packages->find()->orderBy(['Packages.name' => 'ASC']) as $package) {
            $packages[$package->id] = sprintf('%s (%s)', $package->name, $package->id_text);
        }
        if (!$packages) {
            $this->Flash->error('There are no Packages to add/remove');

            return $this->redirect(['action' => 'view', $id]);
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $profile = $this->Profiles->patchEntity($profile, $this->request->getData(), [
                'fields' => ['packages'],
                'associated' => ['Packages' => ['onlyIds' => true]],
            ]);
            if ($this->Profiles->save($profile)) {
                $this->Flash->success('The Profile has been saved.');

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('The Profile could not be saved. Please, try again.');
        }

        $this->set(compact('profile', 'packages'));

        return null;
    }

    /**
     * Removes a package from a profile.
     *
     * @param int $id Profile id.
     * @param int $packageId Package id.
     * @return \Cake\Http\Response|null
     */
    public function removePackage(int $id, int $packageId): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $profile = $this->Profiles->get($id);
        $package = $this->Profiles->Packages->get($packageId);

        $this->Profiles->Packages->unlink($profile, [$package]);
        $this->Flash->success(sprintf("Profile's association with Package '%s' deleted.", $package->name));

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Deletes a profile unless a host uses it as main profile.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response|null
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $blocker = $this->disableBlocker($id);
        if ($blocker !== null) {
            $this->Flash->error($blocker);
        } elseif ($this->Profiles->delete($this->Profiles->get($id))) {
            $this->Flash->success('Profile deleted.');
        } else {
            $this->Flash->error('The Profile could not be deleted. Please, try again.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Enables a profile.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response|null
     */
    public function enable(int $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $profile = $this->Profiles->get($id);
        $profile->enabled = true;
        $this->Profiles->saveOrFail($profile);

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Disables a profile unless a host uses it as main profile.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response|null
     */
    public function disable(int $id): ?Response
    {
        $this->request->allowMethod(['post']);
        $blocker = $this->disableBlocker($id);
        if ($blocker !== null) {
            $this->Flash->error($blocker);
        } else {
            $profile = $this->Profiles->get($id);
            $profile->enabled = false;
            $this->Profiles->saveOrFail($profile);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Explains why a profile cannot be disabled or deleted.
     *
     * @param int $id Profile id.
     * @return string|null Message, null when nothing blocks.
     */
    private function disableBlocker(int $id): ?string
    {
        $profile = $this->Profiles->get($id, contain: ['MainHosts']);
        if (!$profile->main_hosts) {
            return null;
        }

        return sprintf(
            "The Profile '%s' cannot be deleted or disabled because it is the main profile for the following "
            . 'host(s): %s',
            $profile->id_text,
            implode(', ', array_map(fn($host): string => $host->name, $profile->main_hosts)),
        );
    }
}
