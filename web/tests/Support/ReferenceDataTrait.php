<?php

declare(strict_types=1);

namespace App\Test\Support;

use Cake\Datasource\ConnectionManager;

/**
 * Loads the data set the golden feeds were captured from on the legacy application.
 */
trait ReferenceDataTrait
{
    /**
     * Replaces the content of the test database with the reference data set.
     *
     * @return void
     */
    protected function loadReferenceData(): void
    {
        $connection = ConnectionManager::get('test');
        foreach (
            [
            'exit_codes', 'hosts', 'hosts_profiles', 'package_actions', 'package_checks', 'packages',
            'packages_packages', 'packages_profiles', 'profiles', 'profiles_profiles', 'variables',
            ] as $table
        ) {
            $connection->execute('TRUNCATE TABLE `' . $table . '`');
        }

        $script = (string)file_get_contents(TESTS . 'Fixture' . DS . 'reference' . DS . 'fixtures.sql');
        foreach (preg_split('/;\s*\n/', $script) ?: [] as $statement) {
            $statement = trim((string)preg_replace('/^--.*$/m', '', $statement));
            if ($statement !== '') {
                $connection->execute($statement);
            }
        }
    }
}
