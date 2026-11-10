<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the tables of the reports indexer: one summary row per workstation, and the last pass.
 *
 * Dates are Unix timestamps: the log writes local times without a time zone and the files report
 * theirs as timestamps, so no conversion is involved.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- the Migrations plugin resolves classes by file name.
class CreateReportIndex extends BaseMigration
{
    /**
     * Creates both tables.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE `host_reports` (
                `hostname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `log_mtime` bigint DEFAULT NULL,
                `log_size` bigint DEFAULT NULL,
                `xml_mtime` bigint DEFAULT NULL,
                `xml_size` bigint DEFAULT NULL,
                `status` varchar(20) NOT NULL,
                `started_at` bigint DEFAULT NULL,
                `wpkg_version` varchar(50) DEFAULT NULL,
                `profiles` mediumtext NOT NULL,
                `errors` mediumtext NOT NULL,
                `warnings` mediumtext NOT NULL,
                `succeeded` mediumtext NOT NULL,
                `log_error` text DEFAULT NULL,
                `has_xml` tinyint(1) NOT NULL DEFAULT 0,
                `xml_error` text DEFAULT NULL,
                `properties` text NOT NULL,
                `installed_packages` int DEFAULT NULL,
                `file_modified_at` bigint NOT NULL,
                `indexed_at` bigint NOT NULL,
                PRIMARY KEY (`hostname`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `report_index_state` (
                `id` int NOT NULL,
                `finished_at` bigint NOT NULL,
                `duration_ms` int NOT NULL,
                `files_seen` int NOT NULL,
                `files_read` int NOT NULL,
                `hosts_removed` int NOT NULL,
                `error` text DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;
            SQL);
    }

    /**
     * Drops both tables.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DROP TABLE `report_index_state`');
        $this->execute('DROP TABLE `host_reports`');
    }
}
