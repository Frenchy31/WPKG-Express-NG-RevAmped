<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Logs the administrator in and out.
 */
class AuthController extends AppController
{
    /**
     * Opens the login page to anonymous visitors.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['login']);
    }

    /**
     * Shows the login form and handles its submission.
     *
     * @return \Cake\Http\Response|null
     */
    public function login(): ?Response
    {
        $this->request->allowMethod(['get', 'post']);

        if ($this->Authentication->getResult()?->isValid()) {
            return $this->redirect($this->Authentication->getLoginRedirect() ?? '/');
        }
        if ($this->request->is('post')) {
            $this->Flash->error(__('Incorrect username or password'));
        }
        $this->viewBuilder()->setLayout('login');

        return null;
    }

    /**
     * Closes the session.
     *
     * @return \Cake\Http\Response|null
     */
    public function logout(): ?Response
    {
        $this->request->allowMethod(['post']);
        $this->Authentication->logout();
        $this->Flash->success(__('Logged out successfully.'));

        return $this->redirect(['action' => 'login']);
    }
}
