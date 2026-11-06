<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Converts tables restored from the legacy database, usually latin1, to utf8mb4.
 *
 * On a fresh database the tables are already in utf8mb4 and nothing changes.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- the Migrations plugin resolves classes by file name.
class ConvertToUtf8mb4 extends BaseMigration
{
    private const array TABLES = [
        'exit_codes', 'hosts', 'hosts_profiles', 'package_actions', 'package_checks', 'packages',
        'packages_packages', 'packages_profiles', 'profiles', 'profiles_profiles', 'variables',
    ];

    /**
     * Converts every table.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->execute(sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4', $table));
        }
    }

    /**
     * Keeps utf8mb4: converting back could lose characters.
     *
     * @return void
     */
    public function down(): void
    {
    }
}
