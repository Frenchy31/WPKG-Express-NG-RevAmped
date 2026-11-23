<?php

declare(strict_types=1);

namespace App\Test\TestCase\Backup;

use App\Backup\BackupException;
use App\Backup\DumpFormat;
use App\Backup\DumpReader;
use Cake\I18n\I18n;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reading of the export file: what DumpFormat writes is read back, anything else is refused.
 */
class DumpReaderTest extends TestCase
{
    private const array TABLES = ['hosts', 'variables'];

    private const string SCHEMA_VERSION = '20260101000000';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        I18n::setLocale('en_US');
    }

    /**
     * Strings that stress the escaping: every escaped character, and text that looks like SQL.
     *
     * @return array<string, array{string}>
     */
    public static function strings(): array
    {
        return [
            'empty' => [''],
            'quote' => ["it's"],
            'only a quote' => ["'"],
            'backslash' => ['C:\\Program Files\\7-Zip\\'],
            'backslash then quote' => ["\\'"],
            'escaped looking text' => ['\\n \\0 \\Z \\\\ \\\''],
            'line breaks' => ["one\ntwo\r\nthree\rfour"],
            'nul and substitute' => ["a\0b\x1ac"],
            'accents and emoji' => ['été à Noël 😀 日本語'],
            'row separator' => ["),(1,'x"],
            'statement end' => ["'; DROP TABLE hosts; --"],
            'comment and footer' => ["-- End of export: 0 rows\n-- WPKG Express NG database export"],
        ];
    }

    /**
     * A string written by DumpFormat is read back unchanged.
     *
     * @param string $text Text stored in a column.
     * @return void
     */
    #[DataProvider('strings')]
    public function testStringsSurviveWritingAndReading(string $text): void
    {
        $body = DumpFormat::insert('variables', ['id', 'name', 'value'], [[7, $text, null]]);

        [$schemaVersion, $batches] = $this->read($this->file($body, 1));

        $this->assertSame(self::SCHEMA_VERSION, $schemaVersion);
        $this->assertSame([[
            'table' => 'variables',
            'columns' => ['id', 'name', 'value'],
            'rows' => [['7', $text, null]],
        ]], $batches);
    }

    /**
     * Structure statements are checked and skipped, and data statements keep their order.
     *
     * @return void
     */
    public function testReadsEveryStatementOfAnExport(): void
    {
        $createStatement = "CREATE TABLE `hosts` (\n  `id` int NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $body = DumpFormat::structure('hosts', $createStatement)
            . DumpFormat::insert('hosts', ['id', 'name'], [[1, 'a'], [2, 'b']])
            . DumpFormat::structure('variables', str_replace('hosts', 'variables', $createStatement))
            . DumpFormat::insert('variables', ['id'], [[-3]]);

        [, $batches] = $this->read($this->file($body, 3));

        $this->assertSame(
            [
                ['table' => 'hosts', 'columns' => ['id', 'name'], 'rows' => [['1', 'a'], ['2', 'b']]],
                ['table' => 'variables', 'columns' => ['id'], 'rows' => [['-3']]],
            ],
            $batches,
        );
    }

    /**
     * A long statement is handed over in batches, so that an import binds a bounded number of values.
     *
     * @return void
     */
    public function testLongStatementIsSplitInBatches(): void
    {
        $rows = array_map(fn(int $id): array => [$id], range(1, 250));
        $body = DumpFormat::insert('hosts', ['id'], $rows);

        [, $batches] = $this->read($this->file($body, 250));

        $this->assertSame([100, 100, 50], array_map(fn(array $batch): int => count($batch['rows']), $batches));
        $this->assertSame('250', $batches[2]['rows'][49][0]);
    }

    /**
     * A file saved with Windows line endings is still an export.
     *
     * @return void
     */
    public function testWindowsLineEndingsAreAccepted(): void
    {
        $body = DumpFormat::insert('hosts', ['id', 'notes'], [[1, "two\nlines"]]);

        [, $batches] = $this->read(str_replace("\n", "\r\n", $this->file($body, 1)));

        $this->assertSame([['1', "two\nlines"]], $batches[0]['rows']);
    }

    /**
     * Files that do not start like an export are refused before anything is read.
     *
     * @return array<string, array{string}>
     */
    public static function notExports(): array
    {
        $header = DumpFormat::header(self::SCHEMA_VERSION, new DateTimeImmutable('2026-01-01 10:00:00 UTC'));

        return [
            'empty file' => [''],
            'hand-written SQL' => ["DROP TABLE hosts;\n"],
            'SQL without header' => ["INSERT INTO `hosts` (`id`) VALUES\n(1);\n"],
            'binary content' => ["\x89PNG\r\n\x1a\n\0\0\0\rIHDR"],
            'wrong format version' => [str_replace('-- Format: 1', '-- Format: 2', $header)],
            'no schema version' => [str_replace('-- Schema version: ' . self::SCHEMA_VERSION . "\n", '', $header)],
            'schema version with letters' => [str_replace(self::SCHEMA_VERSION, '2026abc', $header)],
            'empty schema version' => [str_replace(self::SCHEMA_VERSION, '', $header)],
            'huge schema version' => [str_replace(self::SCHEMA_VERSION, str_repeat('9', 21), $header)],
            'header in another order' => [implode("\n", array_reverse(explode("\n", $header)))],
        ];
    }

    /**
     * A file that is not an export is refused.
     *
     * @param string $content File content.
     * @return void
     */
    #[DataProvider('notExports')]
    public function testRefusesAFileThatIsNotAnExport(string $content): void
    {
        $reader = new DumpReader($this->stream($content), self::TABLES);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('This file is not an export of this application.');
        $reader->readHeader();
    }

    /**
     * Content a valid header does not make acceptable: statements other than the ones DumpFormat writes.
     *
     * @return array<string, array{string}>
     */
    public static function foreignStatements(): array
    {
        return [
            'drop without backticks' => ["DROP TABLE hosts;\n"],
            'drop without IF EXISTS' => ["DROP TABLE `hosts`;\n"],
            'drop of an unknown table' => ["DROP TABLE IF EXISTS `phinxlog`;\n"],
            'two drops on one line' => ["DROP TABLE IF EXISTS `hosts`; DROP TABLE IF EXISTS `variables`;\n"],
            'create of an unknown table' => ["CREATE TABLE `users` (\n  `id` int\n) ENGINE=InnoDB;\n"],
            'insert into a report table' => ["INSERT INTO `host_reports` (`hostname`) VALUES\n('PC1');\n"],
            'insert with values on the same line' => ["INSERT INTO `hosts` (`id`) VALUES (1);\n"],
            'insert select' => ["INSERT INTO `hosts` (`id`) SELECT 1;\n"],
            'insert with a quoted column' => ["INSERT INTO `hosts` (`id`;DROP TABLE hosts) VALUES\n(1);\n"],
            'too many values' => ["INSERT INTO `hosts` (`id`) VALUES\n(1,2);\n"],
            'too few values' => ["INSERT INTO `hosts` (`id`,`name`) VALUES\n(1);\n"],
            'function call' => ["INSERT INTO `hosts` (`id`) VALUES\n(NOW());\n"],
            'expression' => ["INSERT INTO `hosts` (`id`) VALUES\n(1+1);\n"],
            'hexadecimal literal' => ["INSERT INTO `hosts` (`id`) VALUES\n(0x10);\n"],
            'decimal literal' => ["INSERT INTO `hosts` (`id`) VALUES\n(1.5);\n"],
            'lowercase null' => ["INSERT INTO `hosts` (`id`) VALUES\n(null);\n"],
            'double quoted string' => ["INSERT INTO `hosts` (`id`) VALUES\n(\"a\");\n"],
            'doubled quote' => ["INSERT INTO `hosts` (`id`) VALUES\n('it''s');\n"],
            'unknown escape' => ["INSERT INTO `hosts` (`id`) VALUES\n('a\\qb');\n"],
            'unterminated string' => ["INSERT INTO `hosts` (`id`) VALUES\n('abc);\n"],
            'statement after the rows' => ["INSERT INTO `hosts` (`id`) VALUES\n(1); DROP TABLE hosts;\n"],
            'statement on the next line' => ["INSERT INTO `hosts` (`id`) VALUES\n(1);\nDELETE FROM `hosts`;\n"],
            'row without parentheses' => ["INSERT INTO `hosts` (`id`) VALUES\n1;\n"],
            'row without ending' => ["INSERT INTO `hosts` (`id`) VALUES\n(1)\n"],
            'empty statement' => ["INSERT INTO `hosts` (`id`) VALUES\n;\n"],
            'SET of a variable' => ["SET @a = 1;\n"],
            'SET NAMES of another charset' => ["SET NAMES latin1;\n"],
            'block comment' => ["/* DROP TABLE hosts; */\n"],
            'USE statement' => ["USE mysql;\n"],
            'CREATE closed without a semicolon' => ["CREATE TABLE `hosts` (\n  `id` int\n)\n"],
        ];
    }

    /**
     * A statement the export does not write is refused, so a file cannot carry SQL.
     *
     * @param string $statements Content after the header.
     * @return void
     */
    #[DataProvider('foreignStatements')]
    public function testRefusesStatementsTheExportDoesNotWrite(string $statements): void
    {
        $this->expectException(BackupException::class);
        $this->read($this->file($statements, 1));
    }

    /**
     * A statement is refused as soon as it is read: nothing before it is lost to a late failure.
     *
     * @return void
     */
    public function testNamesTheLineOfTheFirstProblem(): void
    {
        $body = DumpFormat::insert('hosts', ['id'], [[1]]) . "DROP TABLE hosts;\n";

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('Line 11 is not part of an export of this application.');
        $this->read($this->file($body, 1));
    }

    /**
     * Names a table the export does not save.
     *
     * @return void
     */
    public function testNamesAnUnknownTable(): void
    {
        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('Line 8 refers to the table "host_reports", which the export does not save.');
        $this->read($this->file("DROP TABLE IF EXISTS `host_reports`;\n", 0));
    }

    /**
     * Files cut at some point of the layout, and files whose footer is wrong.
     *
     * @return array<string, array{string, string}> Content after the header, and the expected message.
     */
    public static function incompleteBodies(): array
    {
        $insert = DumpFormat::insert('hosts', ['id', 'name'], [[1, 'a'], [2, 'b']]);
        $incomplete = 'The file is incomplete: the end of the export is missing.';

        return [
            'no footer' => [$insert, $incomplete],
            'cut after a statement' => [DumpFormat::insert('hosts', ['id'], [[1]]), $incomplete],
            'cut between rows' => ["INSERT INTO `hosts` (`id`) VALUES\n(1),\n", $incomplete],
            'cut inside a row' => ["INSERT INTO `hosts` (`id`) VALUES\n(1),\n(2,'ab", $incomplete],
            'cut inside a CREATE TABLE' => ["CREATE TABLE `hosts` (\n  `id` int NOT NULL,\n", $incomplete],
            'cut inside the footer' => ["-- End of exp", $incomplete],
        ];
    }

    /**
     * A file without its footer is incomplete.
     *
     * @param string $body Content after the header.
     * @param string $message Expected message.
     * @return void
     */
    #[DataProvider('incompleteBodies')]
    public function testRefusesAFileCutBeforeItsFooter(string $body, string $message): void
    {
        $this->expectException(BackupException::class);
        $this->expectExceptionMessage($message);
        $this->read($this->file($body, null));
    }

    /**
     * The footer counts the rows: a missing or an added row is detected.
     *
     * @return void
     */
    public function testRefusesAFooterThatCountsOtherRows(): void
    {
        $body = DumpFormat::insert('hosts', ['id'], [[1], [2]]);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('The file announces 3 rows but contains 2: it is incomplete or was edited.');
        $this->read($this->file($body, 3));
    }

    /**
     * Nothing may follow the footer.
     *
     * @return void
     */
    public function testRefusesContentAfterTheFooter(): void
    {
        $this->expectException(BackupException::class);
        $this->read($this->file('', 0) . "DROP TABLE IF EXISTS `hosts`;\n");
    }

    /**
     * Blank lines around statements and comments are ignored.
     *
     * @return void
     */
    public function testIgnoresBlankLinesAndComments(): void
    {
        $body = "\n-- a comment\n\n" . DumpFormat::insert('hosts', ['id'], [[1]]) . "\n-- another\n";

        [, $batches] = $this->read($this->file($body, 1) . "\n\n");

        $this->assertCount(1, $batches);
    }

    /**
     * Builds a valid file: header, statements, footer.
     *
     * @param string $body Statements.
     * @param int|null $announcedRows Rows announced by the footer, null to leave the footer out.
     * @return string
     */
    private function file(string $body, ?int $announcedRows): string
    {
        return DumpFormat::header(self::SCHEMA_VERSION, new DateTimeImmutable('2026-01-01 10:00:00 UTC'))
            . $body
            . ($announcedRows === null ? '' : DumpFormat::footer($announcedRows));
    }

    /**
     * Opens a string as a stream.
     *
     * @param string $content Content.
     * @return resource
     */
    private function stream(string $content)
    {
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    /**
     * Reads the header and every batch of a file.
     *
     * @param string $content File content.
     * @return array{0: string, 1: list<array<string, mixed>>} Schema version, batches.
     */
    private function read(string $content): array
    {
        $reader = new DumpReader($this->stream($content), self::TABLES);
        $schemaVersion = $reader->readHeader();

        return [$schemaVersion, iterator_to_array($reader->batches(), false)];
    }
}
