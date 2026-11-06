<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the wpkg-express-ng schema, unchanged apart from utf8mb4.
 *
 * On a database restored from the legacy application, record this migration with
 * `bin/cake migrations mark_migrated` instead of running it.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- the Migrations plugin resolves classes by file name.
class InitialSchema extends BaseMigration
{
    /**
     * Creates every table.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE `exit_codes` (
                `id` int NOT NULL AUTO_INCREMENT,
                `package_action_id` int NOT NULL,
                `code` varchar(11) NOT NULL,
                `reboot` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `hosts` (
                `id` int NOT NULL AUTO_INCREMENT,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `name` varchar(100) NOT NULL,
                `notes` text DEFAULT NULL,
                `created` datetime DEFAULT NULL,
                `modified` datetime DEFAULT NULL,
                `mainprofile_id` int NOT NULL,
                `position` int NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `enabled` (`enabled`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `hosts_profiles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `host_id` int NOT NULL,
                `profile_id` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `package_actions` (
                `id` int NOT NULL AUTO_INCREMENT,
                `package_id` int NOT NULL,
                `type` int NOT NULL,
                `command` varchar(500) NOT NULL,
                `timeout` int DEFAULT NULL,
                `workdir` varchar(500) DEFAULT NULL,
                `position` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `package_checks` (
                `id` int NOT NULL AUTO_INCREMENT,
                `package_id` int NOT NULL,
                `type` int NOT NULL,
                `condition` int NOT NULL,
                `path` varchar(200) DEFAULT NULL,
                `value` varchar(200) DEFAULT NULL,
                `parent_id` int DEFAULT NULL,
                `lft` int DEFAULT NULL,
                `rght` int DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `packages` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `id_text` varchar(100) NOT NULL,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `revision` varchar(35) NOT NULL DEFAULT '0',
                `priority` int NOT NULL DEFAULT 0,
                `reboot` tinyint NOT NULL DEFAULT 0,
                `execute` tinyint NOT NULL DEFAULT 0,
                `notify` tinyint(1) NOT NULL DEFAULT 0,
                `notes` text DEFAULT NULL,
                `created` datetime DEFAULT NULL,
                `modified` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id_text` (`id_text`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `packages_packages` (
                `id` int NOT NULL AUTO_INCREMENT,
                `package_id` int NOT NULL,
                `dependency_id` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `packages_profiles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `profile_id` int NOT NULL,
                `package_id` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `profiles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `id_text` varchar(100) NOT NULL,
                `notes` text DEFAULT NULL,
                `created` datetime DEFAULT NULL,
                `modified` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id_text` (`id_text`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `profiles_profiles` (
                `id` int NOT NULL AUTO_INCREMENT,
                `profile_id` int NOT NULL,
                `dependency_id` int NOT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;

            CREATE TABLE `variables` (
                `id` int NOT NULL AUTO_INCREMENT,
                `ref_id` int NOT NULL,
                `ref_type` int NOT NULL,
                `name` varchar(80) DEFAULT NULL,
                `value` varchar(500) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) DEFAULT CHARSET=utf8mb4;
            SQL);
    }

    /**
     * Drops every table.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute(<<<'SQL'
            DROP TABLE `exit_codes`, `hosts`, `hosts_profiles`, `package_actions`, `package_checks`, `packages`,
                `packages_packages`, `packages_profiles`, `profiles`, `profiles_profiles`, `variables`;
            SQL);
    }
}
