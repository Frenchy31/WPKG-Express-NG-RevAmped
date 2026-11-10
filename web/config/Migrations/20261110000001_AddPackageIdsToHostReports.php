<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds the identifiers of the packages listed in each wpkg.xml to the reports index.
 *
 * The dashboard groups the workstations that run the background agent from them. The indexer reads a
 * file again only when its date or size changed, so the recorded dates are cleared: the next pass
 * reads every wpkg.xml and fills the new column.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- the Migrations plugin resolves classes by file name.
class AddPackageIdsToHostReports extends BaseMigration
{
    /**
     * Adds the column and schedules the re-read of every wpkg.xml.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute('ALTER TABLE `host_reports` ADD COLUMN `package_ids` text NOT NULL AFTER `installed_packages`');
        $this->execute("UPDATE `host_reports` SET `package_ids` = '[]', `xml_mtime` = NULL, `xml_size` = NULL");
    }

    /**
     * Drops the column.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('ALTER TABLE `host_reports` DROP COLUMN `package_ids`');
    }
}
