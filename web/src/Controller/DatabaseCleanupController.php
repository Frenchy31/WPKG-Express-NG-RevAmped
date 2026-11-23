<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cleanup\DatabaseCleanup;

/**
 * Deletes the orphan rows of the database. The Admin page shows them and holds the button.
 */
class DatabaseCleanupController extends AppController
{
    /**
     * Deletes the orphan rows, then shows how many were removed from each table.
     *
     * @return void
     */
    public function run(): void
    {
        $this->request->allowMethod(['post']);
        $cleanup = new DatabaseCleanup();

        $this->set('deleted', $cleanup->clean());
        $this->set('hosts', $cleanup->hostsWithoutMainProfile());
    }
}
