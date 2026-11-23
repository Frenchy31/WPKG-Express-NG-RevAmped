<?php

declare(strict_types=1);

namespace App\Backup;

use Generator;

/**
 * Reads an export file line by line, accepting only the layout written by DumpFormat.
 *
 * Nothing read here is executed as SQL. The reader extracts table names, column names and values, which the
 * caller checks against the database and binds as parameters: a file cannot carry a statement.
 */
final class DumpReader
{
    /**
     * Escape sequences of a string literal, the ones DumpFormat writes.
     */
    private const array ESCAPES = [
        '\\' => '\\',
        "'" => "'",
        '0' => "\0",
        'n' => "\n",
        'r' => "\r",
        'Z' => "\x1a",
    ];

    /**
     * Header of an INSERT statement: the table, then its columns joined by commas.
     */
    private const string INSERT_PATTERN
        = '/^INSERT INTO `([A-Za-z0-9_]+)` \(((?:`[A-Za-z0-9_]+`,)*`[A-Za-z0-9_]+`)\) VALUES$/';

    private int $lineNumber = 0;

    private bool $lastLineHadNoNewline = false;

    private int $rowCount = 0;

    /**
     * @param resource $source Stream positioned at the start of the file.
     * @param list<string> $tables Tables the file may refer to.
     */
    public function __construct(private $source, private readonly array $tables)
    {
    }

    /**
     * Reads the three first lines. Call it before batches().
     *
     * @return string Schema version recorded in the file.
     * @throws \App\Backup\BackupException When the file does not start like an export.
     */
    public function readHeader(): string
    {
        $magic = $this->nextLine();
        $format = $this->nextLine();
        $schemaLine = $this->nextLine() ?? '';
        $schemaVersion = substr($schemaLine, strlen(DumpFormat::SCHEMA_VERSION_PREFIX));

        $isExport = $magic === DumpFormat::MAGIC
            && $format === DumpFormat::FORMAT_LINE
            && str_starts_with($schemaLine, DumpFormat::SCHEMA_VERSION_PREFIX)
            && ctype_digit($schemaVersion)
            && strlen($schemaVersion) <= 20;

        return $isExport ? $schemaVersion : throw new BackupException(
            __('This file is not an export of this application.'),
        );
    }

    /**
     * Reads the data statements, up to DumpFormat::ROWS_PER_STATEMENT rows at a time.
     *
     * The last yielded batch is followed by the check of the footer: a missing footer or a wrong row count throws.
     *
     * @return \Generator<int, array{table: string, columns: list<string>, rows: list<list<string|null>>}>
     * @throws \App\Backup\BackupException When the file does not follow the layout, or is cut.
     */
    public function batches(): Generator
    {
        $announcedRows = null;
        while (($line = $this->nextLine()) !== null) {
            if ($line === '') {
                continue;
            }
            if ($announcedRows !== null) {
                throw $this->invalidLine();
            }

            if (str_starts_with($line, DumpFormat::FOOTER_PREFIX)) {
                $announcedRows = $this->parseFooter($line);
            } elseif (!str_starts_with($line, '--') && $line !== DumpFormat::SET_NAMES) {
                yield from $this->readStatement($line);
            }
        }

        if ($announcedRows === null) {
            throw $this->incomplete();
        }
        if ($announcedRows !== $this->rowCount) {
            throw new BackupException(__(
                'The file announces {0} rows but contains {1}: it is incomplete or was edited.',
                $announcedRows,
                $this->rowCount,
            ));
        }
    }

    /**
     * Reads one statement: a DROP TABLE and a CREATE TABLE are only checked, an INSERT yields its rows.
     *
     * @param string $line First line of the statement.
     * @return \Generator<int, array{table: string, columns: list<string>, rows: list<list<string|null>>}>
     */
    private function readStatement(string $line): Generator
    {
        if (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`;$/', $line, $matches) === 1) {
            $this->assertKnownTable($matches[1]);
        } elseif (preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` \($/', $line, $matches) === 1) {
            $this->assertKnownTable($matches[1]);
            $this->skipStructure();
        } elseif (preg_match(self::INSERT_PATTERN, $line, $matches) === 1) {
            $this->assertKnownTable($matches[1]);

            yield from $this->readRows($matches[1], explode(',', str_replace('`', '', $matches[2])));
        } else {
            throw $this->invalidLine();
        }
    }

    /**
     * Skips the body of a CREATE TABLE, up to the line that closes it.
     *
     * @return void
     */
    private function skipStructure(): void
    {
        while (($line = $this->nextLine()) !== null) {
            if (str_starts_with($line, ')')) {
                if (!str_ends_with($line, ';')) {
                    throw $this->invalidLine();
                }

                return;
            }
        }

        throw $this->incomplete();
    }

    /**
     * Reads the rows of an INSERT, one per line, the last one ending with a semicolon.
     *
     * @param string $table Table name.
     * @param list<string> $columns Column names.
     * @return \Generator<int, array{table: string, columns: list<string>, rows: list<list<string|null>>}>
     */
    private function readRows(string $table, array $columns): Generator
    {
        $rows = [];
        while (($line = $this->nextLine()) !== null) {
            [$values, $isLastRow] = $this->parseRow($line);
            if (count($values) !== count($columns)) {
                throw $this->invalidLine();
            }

            $rows[] = $values;
            $this->rowCount++;
            if ($isLastRow || count($rows) === DumpFormat::ROWS_PER_STATEMENT) {
                yield ['table' => $table, 'columns' => $columns, 'rows' => $rows];
                $rows = [];
            }
            if ($isLastRow) {
                return;
            }
        }

        throw $this->incomplete();
    }

    /**
     * Parses a row line: values between parentheses, then a comma, or a semicolon after the last row.
     *
     * @param string $line Line.
     * @return array{0: list<string|null>, 1: bool} Values, and whether the row ends the statement.
     */
    private function parseRow(string $line): array
    {
        if (($line[0] ?? '') !== '(') {
            throw $this->invalidLine();
        }

        $position = 1;
        $values = [];
        do {
            $values[] = $this->parseValue($line, $position);
            $separator = $line[$position] ?? '';
            $position++;
        } while ($separator === ',');

        $ending = substr($line, $position);
        if ($separator !== ')' || ($ending !== ',' && $ending !== ';')) {
            throw $this->invalidLine();
        }

        return [$values, $ending === ';'];
    }

    /**
     * Parses NULL, an integer or a quoted string. Integers stay strings, the database converts them.
     *
     * @param string $line Line.
     * @param int $position Offset of the value, moved after it.
     * @return string|null
     */
    private function parseValue(string $line, int &$position): ?string
    {
        if (($line[$position] ?? '') === "'") {
            return $this->parseString($line, $position);
        }
        if (substr($line, $position, 4) === 'NULL') {
            $position += 4;

            return null;
        }
        if (preg_match('/\G-?[0-9]+/', $line, $matches, 0, $position) === 1) {
            $position += strlen($matches[0]);

            return $matches[0];
        }

        throw $this->invalidLine();
    }

    /**
     * Parses a quoted string and resolves its escape sequences.
     *
     * @param string $line Line.
     * @param int $position Offset of the opening quote, moved after the closing one.
     * @return string
     */
    private function parseString(string $line, int &$position): string
    {
        $position++;
        $value = '';
        while (true) {
            $length = strcspn($line, "'\\", $position);
            $value .= substr($line, $position, $length);
            $position += $length;

            $delimiter = $line[$position] ?? null;
            $position++;
            if ($delimiter === "'") {
                return $value;
            }
            if ($delimiter === null) {
                throw $this->invalidLine();
            }

            $escaped = $line[$position] ?? '';
            $position++;
            $value .= self::ESCAPES[$escaped] ?? throw $this->invalidLine();
        }
    }

    /**
     * Reads the row count of the footer.
     *
     * @param string $line Footer line.
     * @return int
     */
    private function parseFooter(string $line): int
    {
        $count = substr($line, strlen(DumpFormat::FOOTER_PREFIX), -strlen(DumpFormat::FOOTER_SUFFIX));
        if (!str_ends_with($line, DumpFormat::FOOTER_SUFFIX) || !ctype_digit($count)) {
            throw $this->invalidLine();
        }

        return (int)$count;
    }

    /**
     * Refuses a table the export does not save.
     *
     * @param string $table Table name found in the file.
     * @return void
     */
    private function assertKnownTable(string $table): void
    {
        if (!in_array($table, $this->tables, true)) {
            throw new BackupException(
                __('Line {0} refers to the table "{1}", which the export does not save.', $this->lineNumber, $table),
            );
        }
    }

    /**
     * Reads the next line without its line ending.
     *
     * @return string|null Null at the end of the file.
     */
    private function nextLine(): ?string
    {
        $line = fgets($this->source);
        if ($line === false) {
            return null;
        }

        $this->lineNumber++;
        $this->lastLineHadNoNewline = !str_ends_with($line, "\n");

        return rtrim($line, "\r\n");
    }

    /**
     * Builds the error of the line just read. A last line without line ending means the file is cut.
     *
     * @return \App\Backup\BackupException
     */
    private function invalidLine(): BackupException
    {
        if ($this->lastLineHadNoNewline) {
            return $this->incomplete();
        }

        return new BackupException(__('Line {0} is not part of an export of this application.', $this->lineNumber));
    }

    /**
     * Builds the error of a file that stops before its footer.
     *
     * @return \App\Backup\BackupException
     */
    private function incomplete(): BackupException
    {
        return new BackupException(__('The file is incomplete: the end of the export is missing.'));
    }
}
