<?php

declare(strict_types=1);

namespace App\Test\TestCase\Backup;

use App\Backup\BackupException;
use App\Backup\BackupRepository;
use App\Backup\DatabaseBackup;
use App\Backup\DumpFormat;
use App\Test\Support\DatabaseSnapshotTrait;
use App\Test\Support\ReferenceDataTrait;
use Cake\Core\Configure;
use Cake\Database\Exception\QueryException;
use Cake\I18n\I18n;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Export, import and reset of the business tables, on the reference data set of the golden feeds.
 */
class DatabaseBackupTest extends TestCase
{
    use DatabaseSnapshotTrait;
    use ReferenceDataTrait;

    private DatabaseBackup $backup;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        I18n::setLocale('en_US');
        $this->loadReferenceData();
        $this->backup = new DatabaseBackup(new BackupRepository($this->testConnection()));
    }

    /**
     * The file starts with the schema version of the database, the last migration applied.
     *
     * @return void
     */
    public function testExportRecordsTheSchemaVersion(): void
    {
        $migrations = glob(CONFIG . 'Migrations' . DS . '*.php') ?: [];
        $lastMigration = max(array_map(fn(string $path): int => (int)basename($path), $migrations));

        $lines = explode("\n", $this->export());

        $this->assertSame('-- WPKG Express NG database export', $lines[0]);
        $this->assertSame('-- Format: 1', $lines[1]);
        $this->assertSame('-- Schema version: ' . $lastMigration, $lines[2]);
        $this->assertSame('-- Created: 2026-11-23T10:45:12+00:00', $lines[3]);
    }

    /**
     * The file holds the structure and the rows of every business table, and nothing else.
     *
     * @return void
     */
    public function testExportHoldsEveryBusinessTableAndNothingElse(): void
    {
        $connection = $this->testConnection();
        $connection->execute(
            'INSERT INTO `host_reports` (`hostname`, `status`, `profiles`, `errors`, `warnings`, `succeeded`,'
            . " `properties`, `package_ids`, `file_modified_at`, `indexed_at`) VALUES ('PC-REPORT', 'success',"
            . " '[]', '[]', '[]', '[]', '{}', '[]', 0, 0)",
        );

        try {
            $file = $this->export();
        } finally {
            $connection->execute("DELETE FROM `host_reports` WHERE `hostname` = 'PC-REPORT'");
        }

        foreach (BackupRepository::TABLES as $table) {
            $this->assertStringContainsString("DROP TABLE IF EXISTS `$table`;\nCREATE TABLE `$table` (", $file);
            $this->assertStringContainsString("INSERT INTO `$table` (`id`,", $file, $table);
        }
        $this->assertStringNotContainsString('PC-REPORT', $file);
        $this->assertStringNotContainsString('host_reports', $file);
        $this->assertStringNotContainsString('report_index_state', $file);
        $this->assertStringNotContainsString('phinxlog', $file);
        $this->assertStringNotContainsString('cake_migrations', $file);
        $this->assertStringEndsWith(DumpFormat::footer($this->rowCount()), $file);
    }

    /**
     * Exporting, emptying the database and importing the file restores the three feeds, byte for byte.
     *
     * @return void
     */
    public function testExportResetImportRestoresTheFeedsByteForByte(): void
    {
        $feeds = $this->feedDocuments();
        $file = $this->export();

        $this->backup->reset();
        $this->assertNotSame($feeds, $this->feedDocuments(), 'The reset left the feeds as they were.');
        $this->assertSame(0, $this->rowCount());

        $this->backup->import($this->open($file));

        $this->assertSame($feeds, $this->feedDocuments());
    }

    /**
     * Every row comes back as it was, including the nested set of the checks.
     *
     * @return void
     */
    public function testImportRestoresEveryRowAndTheNestedSetOfTheChecks(): void
    {
        $snapshot = $this->snapshot();
        $this->assertNotEmpty(array_filter($snapshot['package_checks'], fn(array $check): bool => $check['lft'] > 0));
        $file = $this->export();

        $this->backup->reset();
        $rowCount = $this->backup->import($this->open($file));

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(array_sum(array_map('count', $snapshot)), $rowCount);
    }

    /**
     * Rows added after the export disappear: an import replaces the content, it does not merge.
     *
     * @return void
     */
    public function testImportReplacesTheCurrentContent(): void
    {
        $snapshot = $this->snapshot();
        $file = $this->export();
        $connection = $this->testConnection();
        $connection->execute("INSERT INTO `variables` (`ref_id`, `ref_type`, `name`, `value`) VALUES (1, 2, 'X', 'y')");
        $connection->execute("DELETE FROM `hosts` WHERE `id` = 1");

        $this->backup->import($this->open($file));

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Quotes, backslashes, line breaks, NUL, accents and 4-byte characters survive the file.
     *
     * @return void
     */
    public function testEveryKindOfCharacterSurvivesTheRoundTrip(): void
    {
        $text = "quote ' double \" backslash \\ \\' \\n line\nbreak\r\nnul \0 sub \x1a semicolon; comment -- "
            . "accents été à Noël emoji 😀 日本語 end ),(1,'x') -- End of export: 0 rows";
        $connection = $this->testConnection();
        $connection->execute('UPDATE `hosts` SET `notes` = ? WHERE `id` = 1', [$text]);
        $connection->execute('UPDATE `variables` SET `value` = ? WHERE `id` = 1', [$text]);
        $snapshot = $this->snapshot();
        $file = $this->export();

        $this->backup->reset();
        $this->backup->import($this->open($file));

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame($text, $connection->execute('SELECT `notes` FROM `hosts` WHERE `id` = 1')->fetchColumn(0));
    }

    /**
     * NULL, zero and empty strings stay distinct.
     *
     * @return void
     */
    public function testNullZeroAndEmptyStringStayDistinct(): void
    {
        $connection = $this->testConnection();
        $hostIds = array_column($this->snapshot()['hosts'], 'id');
        $this->assertGreaterThanOrEqual(3, count($hostIds));
        foreach ([null, '', '0'] as $position => $notes) {
            $connection->execute('UPDATE `hosts` SET `notes` = ? WHERE `id` = ?', [$notes, $hostIds[$position]]);
        }
        $snapshot = $this->snapshot();
        $file = $this->export();

        $this->backup->reset();
        $this->backup->import($this->open($file));

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame([null, '', '0'], array_slice(array_column($this->snapshot()['hosts'], 'notes'), 0, 3));
    }

    /**
     * A table with more rows than one statement holds is exported and imported in several statements.
     *
     * @return void
     */
    public function testTablesLargerThanOneStatementSurvive(): void
    {
        $connection = $this->testConnection();
        $connection->execute('DELETE FROM `variables`');
        for ($batch = 0; $batch < 3; $batch++) {
            $values = [];
            for ($row = 0; $row < DumpFormat::ROWS_PER_STATEMENT; $row++) {
                $values[] = sprintf("(1, 2, 'N%d-%d', 'v')", $batch, $row);
            }
            $connection->execute(
                'INSERT INTO `variables` (`ref_id`, `ref_type`, `name`, `value`) VALUES ' . implode(',', $values),
            );
        }
        $snapshot = $this->snapshot();
        $file = $this->export();

        $this->assertSame(3, substr_count($file, 'INSERT INTO `variables`'));
        $this->backup->reset();
        $this->backup->import($this->open($file));

        $this->assertCount(300, $snapshot['variables']);
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Cut points inside a file: every one is refused and the database stays as it was.
     *
     * @return array<string, array{int}> Share of the file kept, in percent.
     */
    public static function cutPoints(): array
    {
        $cuts = [];
        foreach ([1, 5, 10, 20, 33, 50, 66, 75, 90, 95, 99] as $percent) {
            $cuts[$percent . '% of the file'] = [$percent];
        }

        return $cuts;
    }

    /**
     * A truncated file is refused with an error, and the database stays as it was.
     *
     * @param int $percent Share of the file kept.
     * @return void
     */
    #[DataProvider('cutPoints')]
    public function testTruncatedFileIsRefusedAndTheDatabaseIsUnchanged(int $percent): void
    {
        $snapshot = $this->snapshot();
        $file = $this->export();
        $truncated = substr($file, 0, intdiv(strlen($file) * $percent, 100));

        try {
            $this->backup->import($this->open($truncated));
            $this->fail('A truncated file was imported.');
        } catch (BackupException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A file cut exactly after the last data statement lacks its footer: refused, nothing changes.
     *
     * @return void
     */
    public function testFileCutBeforeItsFooterIsRefused(): void
    {
        $snapshot = $this->snapshot();
        $file = $this->export();
        $withoutFooter = substr($file, 0, (int)strrpos($file, DumpFormat::FOOTER_PREFIX));

        try {
            $this->backup->import($this->open($withoutFooter));
            $this->fail('A file without footer was imported.');
        } catch (BackupException $exception) {
            $this->assertSame('The file is incomplete: the end of the export is missing.', $exception->getMessage());
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Hand-written SQL is refused, and the table it targets is still there.
     *
     * @return void
     */
    public function testHandWrittenSqlFileIsRefused(): void
    {
        $snapshot = $this->snapshot();

        try {
            $this->backup->import($this->open("DROP TABLE hosts;\n"));
            $this->fail('A hand-written file was imported.');
        } catch (BackupException $exception) {
            $this->assertSame('This file is not an export of this application.', $exception->getMessage());
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Whatever the header says, a statement is never run: DROP TABLE is read and skipped.
     *
     * @return void
     */
    public function testStatementsOfTheFileAreReadNeverRun(): void
    {
        $file = $this->export();
        $file = substr($file, 0, (int)strpos($file, 'DROP TABLE IF EXISTS'))
            . "DROP TABLE IF EXISTS `hosts`;\n"
            . DumpFormat::footer(0);

        $rowCount = $this->backup->import($this->open($file));

        $this->assertSame(0, $rowCount);
        $this->assertSame(
            '0',
            (string)$this->testConnection()->execute('SELECT COUNT(*) FROM `hosts`')->fetchColumn(0),
        );
    }

    /**
     * A valid header does not make arbitrary SQL acceptable.
     *
     * @return array<string, array{string}>
     */
    public static function forgedFiles(): array
    {
        return [
            'DROP TABLE' => ["DROP TABLE hosts;\n"],
            'DELETE' => ["DELETE FROM `hosts`;\n"],
            'INSERT into a table the export does not save' => [
                "INSERT INTO `host_reports` (`hostname`) VALUES\n('x');\n",
            ],
            'INSERT into the migration table' => ["INSERT INTO `phinxlog` (`version`) VALUES\n(1);\n"],
            'INSERT with a SELECT' => ["INSERT INTO `hosts` (`id`) SELECT `id` FROM `packages`;\n"],
            'column of another table' => ["INSERT INTO `variables` (`id`,`secret`) VALUES\n(1,2);\n"],
            'missing columns' => ["INSERT INTO `variables` (`id`,`ref_id`) VALUES\n(1,2);\n"],
            'duplicated column' => [
                "INSERT INTO `variables` (`id`,`ref_id`,`ref_type`,`name`,`name`) VALUES\n(1,2,3,'a','b');\n",
            ],
        ];
    }

    /**
     * A file with the header of an export and foreign content is refused, and nothing changes.
     *
     * @param string $statements Content between the header and the footer.
     * @return void
     */
    #[DataProvider('forgedFiles')]
    public function testForgedFileIsRefusedAndTheDatabaseIsUnchanged(string $statements): void
    {
        $snapshot = $this->snapshot();

        try {
            $this->backup->import($this->open($this->header() . $statements . DumpFormat::footer(1)));
            $this->fail('A forged file was imported.');
        } catch (BackupException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(
            (string)count($snapshot['hosts']),
            (string)$this->testConnection()->execute('SELECT COUNT(*) FROM `hosts`')->fetchColumn(0),
        );
    }

    /**
     * A file that lists other columns than the table has is refused, even when the database would accept the rows.
     *
     * @return void
     */
    public function testFileWithOtherColumnsThanTheTableIsRefused(): void
    {
        $snapshot = $this->snapshot();
        $statement = "INSERT INTO `hosts` (`id`,`name`,`mainprofile_id`) VALUES\n(1,'x',1);\n";

        try {
            $this->backup->import($this->open($this->header() . $statement . DumpFormat::footer(1)));
            $this->fail('A file with fewer columns was imported.');
        } catch (BackupException $exception) {
            $this->assertSame(
                'The columns of the table "hosts" in the file differ from the database.',
                $exception->getMessage(),
            );
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * An export of another schema version is refused with both versions in the message.
     *
     * @return void
     */
    public function testSchemaVersionMismatchIsRefused(): void
    {
        $snapshot = $this->snapshot();
        $file = $this->export();
        $databaseVersion = (new BackupRepository($this->testConnection()))->schemaVersion();
        $older = str_replace('-- Schema version: ' . $databaseVersion, '-- Schema version: 20260101000000', $file);

        try {
            $this->backup->import($this->open($older));
            $this->fail('An export of another schema version was imported.');
        } catch (BackupException $exception) {
            $this->assertSame(
                'The file was exported from schema version 20260101000000, the database is at version '
                . $databaseVersion . '. Import it into a database at the same version.',
                $exception->getMessage(),
            );
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A footer that counts other rows than the file holds is refused.
     *
     * @return void
     */
    public function testFooterWithAnotherRowCountIsRefused(): void
    {
        $snapshot = $this->snapshot();
        $file = $this->export();
        $edited = str_replace(DumpFormat::footer($this->rowCount()), DumpFormat::footer($this->rowCount() + 1), $file);

        try {
            $this->backup->import($this->open($edited));
            $this->fail('A file with a wrong row count was imported.');
        } catch (BackupException $exception) {
            $this->assertStringContainsString('it is incomplete or was edited', $exception->getMessage());
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A failure in the middle of the import undoes everything the import already did, tables emptied included.
     *
     * @return void
     */
    public function testDatabaseErrorUndoesTheWholeImport(): void
    {
        $snapshot = $this->snapshot();
        $lines = explode("\n", $this->export());
        $inserts = array_filter($lines, fn(string $line): bool => str_starts_with($line, 'INSERT INTO `variables`'));
        $insertLine = array_key_last($inserts);
        $firstRow = $lines[$insertLine + 1];
        $lines[$insertLine + 1] = substr($firstRow, 0, -1) . ',';
        array_splice($lines, $insertLine + 2, 0, [substr($firstRow, 0, -1) . substr($firstRow, -1)]);
        $duplicated = implode("\n", $lines);

        try {
            $this->backup->import($this->open($duplicated));
            $this->fail('A file with a duplicated row was imported.');
        } catch (BackupException $exception) {
            $this->assertStringStartsWith('The database rejected the file: SQLSTATE[23000]', $exception->getMessage());
            $this->assertInstanceOf(QueryException::class, $exception->getPrevious());
        }

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertGreaterThan(1, count($snapshot['exit_codes']), 'The tables before `variables` hold rows to undo.');
    }

    /**
     * A reset empties the business tables and leaves the report tables to the indexer.
     *
     * @return void
     */
    public function testResetEmptiesTheBusinessTablesOnly(): void
    {
        $connection = $this->testConnection();
        $connection->execute(
            'INSERT INTO `host_reports` (`hostname`, `status`, `profiles`, `errors`, `warnings`, `succeeded`,'
            . " `properties`, `package_ids`, `file_modified_at`, `indexed_at`) VALUES ('PC-KEEP', 'success',"
            . " '[]', '[]', '[]', '[]', '{}', '[]', 0, 0)",
        );

        try {
            $this->backup->reset();
            $keptReports = $connection->execute("SELECT COUNT(*) FROM `host_reports` WHERE `hostname` = 'PC-KEEP'")
                ->fetchColumn(0);
        } finally {
            $connection->execute("DELETE FROM `host_reports` WHERE `hostname` = 'PC-KEEP'");
        }

        $this->assertSame(0, $this->rowCount());
        $this->assertSame(1, (int)$keptReports);
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<packages/>' . "\n",
            $this->feedDocuments()['packages, indented'],
        );
    }

    /**
     * Tables that cannot roll back make import and reset refuse to start: a failure would leave them half replaced.
     *
     * @return void
     */
    public function testTablesWithoutTransactionsBlockImportAndReset(): void
    {
        $repository = new class ($this->testConnection()) extends BackupRepository {
            /**
             * @inheritDoc
             */
            public function nonTransactionalTables(): array
            {
                return ['variables' => 'MyISAM'];
            }
        };
        $backup = new DatabaseBackup($repository);
        $snapshot = $this->snapshot();
        $file = $this->export();
        $message = 'The table "variables" uses the MyISAM storage engine, which cannot undo a failed operation. '
            . 'Convert it to InnoDB first.';

        foreach ([fn() => $backup->import($this->open($file)), fn() => $backup->reset()] as $operation) {
            try {
                $operation();
                $this->fail('The operation ran on a table without transactions.');
            } catch (BackupException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * The business tables of a regular installation are InnoDB, and the check finds a table that is not.
     *
     * @return void
     */
    public function testRepositoryFindsTablesWithoutTransactions(): void
    {
        $connection = $this->testConnection();
        $repository = new BackupRepository($connection);
        $this->assertSame([], $repository->nonTransactionalTables());

        try {
            $connection->execute('ALTER TABLE `variables` ENGINE = MyISAM');
        } catch (QueryException) {
            $this->markTestSkipped('The MyISAM storage engine is not available on this server.');
        }

        try {
            $this->assertSame(['variables' => 'MyISAM'], $repository->nonTransactionalTables());
        } finally {
            $connection->execute('ALTER TABLE `variables` ENGINE = InnoDB');
        }
        $this->assertSame([], $repository->nonTransactionalTables());
    }

    /**
     * The schema version is read where the Migrations plugin records its migrations: `phinxlog` on a database
     * created by an older plugin, `cake_migrations` on a newer one. Whichever of the two the test database lacks is
     * created for the test, holding a recognizable version.
     *
     * @return void
     */
    public function testSchemaVersionFollowsTheTableTheMigrationsPluginUses(): void
    {
        $connection = $this->testConnection();
        $dialect = $connection->getDriver()->schemaDialect();
        $hasLegacyTable = $dialect->hasTable('phinxlog');
        $hasUnifiedTable = $dialect->hasTable('cake_migrations');
        $migrations = glob(CONFIG . 'Migrations' . DS . '*.php') ?: [];
        $applied = (string)max(array_map(fn(string $path): int => (int)basename($path), $migrations));
        $sentinel = '29991231235959';
        $repository = new BackupRepository($connection);

        try {
            if (!$hasLegacyTable) {
                $connection->execute('CREATE TABLE `phinxlog` (`version` BIGINT NOT NULL PRIMARY KEY)');
                $connection->execute('INSERT INTO `phinxlog` (`version`) VALUES (' . $sentinel . ')');
            }
            if (!$hasUnifiedTable) {
                $connection->execute(
                    'CREATE TABLE `cake_migrations` (`version` BIGINT NOT NULL, `plugin` VARCHAR(100))',
                );
                $connection->execute(
                    'INSERT INTO `cake_migrations` (`version`, `plugin`) VALUES '
                    . '(' . $sentinel . ', NULL), (' . ((int)$sentinel + 1) . ", 'SomePlugin')",
                );
            }

            Configure::write('Migrations.legacyTables', true);
            $this->assertSame($hasLegacyTable ? $applied : $sentinel, $repository->schemaVersion());

            Configure::write('Migrations.legacyTables', false);
            $this->assertSame($hasUnifiedTable ? $applied : $sentinel, $repository->schemaVersion());

            Configure::delete('Migrations.legacyTables');
            $this->assertSame($hasLegacyTable ? $applied : $sentinel, $repository->schemaVersion());
        } finally {
            Configure::delete('Migrations.legacyTables');
            foreach ([['phinxlog', $hasLegacyTable], ['cake_migrations', $hasUnifiedTable]] as [$table, $existed]) {
                if (!$existed) {
                    $connection->execute('DROP TABLE IF EXISTS `' . $table . '`');
                }
            }
        }
    }

    /**
     * Table and column names reach SQL only when they are known names.
     *
     * @return void
     */
    public function testRepositoryRefusesNamesThatAreNotBusinessTables(): void
    {
        $repository = new BackupRepository($this->testConnection());

        $this->expectException(\InvalidArgumentException::class);
        $repository->columnNames('hosts`; DROP TABLE `hosts');
    }

    /**
     * Column names reach SQL only as plain identifiers.
     *
     * @return void
     */
    public function testRepositoryRefusesColumnNamesThatAreNotIdentifiers(): void
    {
        $repository = new BackupRepository($this->testConnection());

        $this->expectException(\InvalidArgumentException::class);
        $repository->insertRows('hosts', ['id`) SELECT 1 -- '], [['1']]);
    }

    /**
     * Returns the header of an export of the current database, up to its blank line.
     *
     * @return string
     */
    private function header(): string
    {
        return implode("\n", array_slice(explode("\n", $this->export()), 0, 7)) . "\n\n";
    }

    /**
     * Writes the export of the current database, dated.
     *
     * @return string
     */
    private function export(): string
    {
        return (string)$this->backup->export(new DateTimeImmutable('2026-11-23 10:45:12 UTC'));
    }

    /**
     * Opens a string as a stream, as the controller hands an uploaded file over.
     *
     * @param string $content Content.
     * @return resource
     */
    private function open(string $content)
    {
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }
}
