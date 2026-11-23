<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cleanup\CleanupException;
use App\Cleanup\DatabaseCleanup;
use Cake\Http\Response;

/**
 * Deletes the orphan rows of the database. The Admin page shows them and holds the button.
 */
class DatabaseCleanupController extends AppController
{
    /**
     * Deletes the orphan rows, then shows how many were removed from each table. On failure nothing is deleted,
     * and the Admin page shows the reason.
     *
     * @return \Cake\Http\Response|null
     */
    public function run(): ?Response
    {
        $this->request->allowMethod(['post']);
        $cleanup = new DatabaseCleanup();

        try {
            $deleted = $cleanup->clean();
        } catch (CleanupException $exception) {
            $this->Flash->error($exception->getMessage());

            return $this->redirect(['controller' => 'Admin', 'action' => 'index']);
        }

        $this->set('deleted', $deleted);
        $this->set('hosts', $cleanup->hostsWithoutMainProfile());

        return null;
    }
}
