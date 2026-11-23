<?php

declare(strict_types=1);

namespace App\Backup;

use Cake\Database\Exception\QueryException;
use DateTimeInterface;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * Exports, imports and empties the business tables: hosts, profiles, packages and what belongs to them.
 *
 * An import or a reset changes what the workstations read in hosts.xml, profiles.xml and packages.xml: a package
 * missing from the new feeds is uninstalled at their next run. Both are one transaction, so a failure leaves the
 * database as it was.
 */
class DatabaseBackup
{
    /**
     * Size above which the export spills from memory to a temporary file.
     */
    private const int MEMORY_LIMIT_BYTES = 2 * 1024 * 1024;

    /**
     * @param \App\Backup\BackupRepository $repository SQL of the backup.
     */
    public function __construct(private readonly BackupRepository $repository)
    {
    }

    /**
     * Writes the structure and the rows of the business tables, as one consistent snapshot.
     *
     * The file is complete before the first byte is sent, so a failure never produces a cut download.
     *
     * @param \DateTimeInterface $createdAt Date recorded in the header.
     * @return \Psr\Http\Message\StreamInterface Stream positioned at the start of the file.
     */
    public function export(DateTimeInterface $createdAt): StreamInterface
    {
        $stream = new Stream(fopen('php://temp/maxmemory:' . self::MEMORY_LIMIT_BYTES, 'w+b'));

        $this->repository->transactional(function () use ($stream, $createdAt): void {
            $stream->write(DumpFormat::header($this->repository->schemaVersion(), $createdAt));

            $rowCount = 0;
            foreach (BackupRepository::TABLES as $table) {
                $stream->write(DumpFormat::structure($table, $this->repository->createStatement($table)));

                $lastId = 0;
                while (($rows = $this->repository->rowsAfter($table, $lastId, DumpFormat::ROWS_PER_STATEMENT)) !== []) {
                    $columns = array_keys($rows[0]);
                    $stream->write(DumpFormat::insert($table, $columns, array_map(array_values(...), $rows)));
                    $lastId = (int)$rows[array_key_last($rows)]['id'];
                    $rowCount += count($rows);
                }
            }
            $stream->write(DumpFormat::footer($rowCount));
        });
        $stream->rewind();

        return $stream;
    }

    /**
     * Replaces the content of the business tables with the rows of an export.
     *
     * The file must come from this application at the schema version of the database. It is parsed, never run as
     * SQL, and applied in one transaction: the first error restores the previous content.
     *
     * @param resource $source Stream of the file, positioned at its start.
     * @return int Number of rows imported.
     * @throws \App\Backup\BackupException When the file is refused or the database rejects its rows.
     */
    public function import($source): int
    {
        $reader = new DumpReader($source, BackupRepository::TABLES);
        $fileVersion = $reader->readHeader();
        $databaseVersion = $this->repository->schemaVersion();
        if ($fileVersion !== $databaseVersion) {
            throw new BackupException(__(
                'The file was exported from schema version {0}, the database is at version {1}. '
                . 'Import it into a database at the same version.',
                $fileVersion,
                $databaseVersion,
            ));
        }
        $this->assertTransactional();

        try {
            return $this->repository->transactional(function () use ($reader): int {
                $this->repository->deleteAll();

                $rowCount = 0;
                $sortedColumnsByTable = [];
                foreach ($reader->batches() as ['table' => $table, 'columns' => $columns, 'rows' => $rows]) {
                    $sortedColumnsByTable[$table] ??= $this->sorted($this->repository->columnNames($table));
                    if ($this->sorted($columns) !== $sortedColumnsByTable[$table]) {
                        throw new BackupException(
                            __('The columns of the table "{0}" in the file differ from the database.', $table),
                        );
                    }

                    $this->repository->insertRows($table, $columns, $rows);
                    $rowCount += count($rows);
                }

                return $rowCount;
            });
        } catch (QueryException $exception) {
            throw new BackupException(
                __('The database rejected the file: {0}', $exception->getPrevious()?->getMessage() ?? ''),
                0,
                $exception,
            );
        }
    }

    /**
     * Deletes every row of the business tables, in one transaction.
     *
     * @return void
     * @throws \App\Backup\BackupException When a table cannot be rolled back.
     */
    public function reset(): void
    {
        $this->assertTransactional();
        $this->repository->transactional(fn() => $this->repository->deleteAll());
    }

    /**
     * Refuses to run when a failure could leave a half-replaced database.
     *
     * @return void
     * @throws \App\Backup\BackupException When a business table does not use InnoDB.
     */
    private function assertTransactional(): void
    {
        $engines = $this->repository->nonTransactionalTables();
        if ($engines !== []) {
            throw new BackupException(__(
                'The table "{0}" uses the {1} storage engine, which cannot undo a failed operation. '
                . 'Convert it to InnoDB first.',
                array_key_first($engines),
                reset($engines),
            ));
        }
    }

    /**
     * Sorts column names, to compare two lists whatever their order.
     *
     * @param list<string> $columns Column names.
     * @return list<string>
     */
    private function sorted(array $columns): array
    {
        sort($columns);

        return $columns;
    }
}
