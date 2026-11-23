<?php

declare(strict_types=1);

namespace App\Backup;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Closure;
use InvalidArgumentException;

/**
 * SQL of the backup: reads and replaces the content of the business tables.
 *
 * `host_reports` and `report_index_state` are not business tables: the indexer rebuilds them from the share.
 */
class BackupRepository
{
    /**
     * Tables saved by an export, emptied by a reset and replaced by an import.
     */
    public const array TABLES = [
        'exit_codes', 'hosts', 'hosts_profiles', 'package_actions', 'package_checks', 'packages',
        'packages_packages', 'packages_profiles', 'profiles', 'profiles_profiles', 'variables',
    ];

    /**
     * @param \Cake\Database\Connection $connection Connection of the application database.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Returns the version of the last migration applied to the database.
     *
     * @return string Digits of the migration file name, empty when no migration is recorded.
     */
    public function schemaVersion(): string
    {
        $usesLegacyTable = Configure::read('Migrations.legacyTables')
            ?? $this->connection->getDriver()->schemaDialect()->hasTable('phinxlog');
        $sql = $usesLegacyTable
            ? 'SELECT MAX(`version`) FROM `phinxlog`'
            : 'SELECT MAX(`version`) FROM `cake_migrations` WHERE `plugin` IS NULL';

        return (string)$this->connection->execute($sql)->fetchColumn(0);
    }

    /**
     * Lists the tables whose storage engine cannot roll a transaction back.
     *
     * @return array<string, string> Engine by table name.
     */
    public function nonTransactionalTables(): array
    {
        $names = implode(', ', array_map(fn(string $table): string => "'" . $table . "'", self::TABLES));
        $rows = $this->connection->execute(
            'SELECT `TABLE_NAME` AS `name`, `ENGINE` AS `engine` FROM `information_schema`.`TABLES`'
            . ' WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (' . $names . ')'
            . " AND `ENGINE` <> 'InnoDB' ORDER BY `TABLE_NAME`",
        )->fetchAll('assoc');

        return array_column($rows, 'engine', 'name');
    }

    /**
     * Returns the column names of a table, in table order.
     *
     * @param string $table Business table.
     * @return list<string>
     */
    public function columnNames(string $table): array
    {
        $rows = $this->connection->execute('SHOW COLUMNS FROM ' . $this->quotedTable($table))->fetchAll('assoc');

        return array_column($rows, 'Field');
    }

    /**
     * Returns the statement that creates a table.
     *
     * @param string $table Business table.
     * @return string Statement without its final semicolon.
     */
    public function createStatement(string $table): string
    {
        $row = $this->connection->execute('SHOW CREATE TABLE ' . $this->quotedTable($table))->fetch('assoc');

        return (string)$row['Create Table'];
    }

    /**
     * Reads the next rows of a table by primary key, as stored: no type conversion.
     *
     * @param string $table Business table.
     * @param int $lastId Greatest id already read, 0 to start.
     * @param int $limit Maximum number of rows.
     * @return list<array<string, mixed>>
     */
    public function rowsAfter(string $table, int $lastId, int $limit): array
    {
        $sql = sprintf('SELECT * FROM %s WHERE `id` > ? ORDER BY `id` LIMIT %d', $this->quotedTable($table), $limit);

        return array_values($this->connection->execute($sql, [$lastId], ['integer'])->fetchAll('assoc'));
    }

    /**
     * Deletes every row of the business tables. Not TRUNCATE: it commits the running transaction.
     *
     * @return void
     */
    public function deleteAll(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->execute('DELETE FROM ' . $this->quotedTable($table));
        }
    }

    /**
     * Inserts rows with bound values, never as SQL text.
     *
     * @param string $table Business table.
     * @param list<string> $columns Column names.
     * @param list<list<string|null>> $rows Values of each row, in the order of the columns.
     * @return void
     * @throws \InvalidArgumentException When a column name is not a plain identifier.
     */
    public function insertRows(string $table, array $columns, array $rows): void
    {
        $quotedColumns = array_map(function (string $column): string {
            if (preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid column name `%s`.', $column));
            }

            return '`' . $column . '`';
        }, $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $this->connection->execute(
            sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $this->quotedTable($table),
                implode(', ', $quotedColumns),
                implode(', ', array_fill(0, count($rows), $placeholders)),
            ),
            array_merge(...$rows),
        );
    }

    /**
     * Runs a callback in a transaction: committed when it returns, rolled back when it throws.
     *
     * @param \Closure $callback Callback.
     * @return mixed Result of the callback.
     */
    public function transactional(Closure $callback): mixed
    {
        return $this->connection->transactional($callback);
    }

    /**
     * Quotes the name of a business table, the only table names that may enter an SQL text.
     *
     * @param string $table Table name.
     * @return string
     * @throws \InvalidArgumentException When the table is not a business table.
     */
    private function quotedTable(string $table): string
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException(sprintf('`%s` is not a business table.', $table));
        }

        return '`' . $table . '`';
    }
}
