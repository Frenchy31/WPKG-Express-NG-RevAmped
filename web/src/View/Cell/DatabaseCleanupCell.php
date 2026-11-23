<?php

declare(strict_types=1);

namespace App\View\Cell;

use App\Cleanup\DatabaseCleanup;
use App\Cleanup\OrphanTable;
use Cake\View\Cell;

/**
 * Orphan rows of the database and the button deleting them, for the Admin page.
 */
class DatabaseCleanupCell extends Cell
{
    /**
     * Counts the orphan rows. Nothing changes until the button is used.
     *
     * @return void
     */
    public function display(): void
    {
        $cleanup = new DatabaseCleanup();
        $counts = $cleanup->orphanCounts();

        $rowsToDelete = 0;
        foreach ($counts as $table => $count) {
            if (OrphanTable::from($table)->isCleaned()) {
                $rowsToDelete += $count;
            }
        }

        $this->set(compact('counts', 'rowsToDelete'));
        $this->set('hosts', $cleanup->hostsWithoutMainProfile());
    }
}
