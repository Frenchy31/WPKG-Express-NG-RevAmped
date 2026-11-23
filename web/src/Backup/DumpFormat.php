<?php

declare(strict_types=1);

namespace App\Backup;

use DateTimeInterface;

/**
 * Writes the export file, an SQL file that a database client can replay and that DumpReader parses.
 *
 * Layout, one statement per line group so that the reader never needs an SQL parser:
 *
 *   header comments, SET NAMES
 *   per table: DROP TABLE IF EXISTS, CREATE TABLE, INSERT INTO with one row per line
 *   footer comment with the number of rows
 *
 * Strings escape only backslash, quote, NUL, line feed, carriage return and Ctrl-Z, so a row never spans two lines.
 */
final class DumpFormat
{
    public const string MAGIC = '-- WPKG Express NG database export';

    /**
     * Version of the layout: raise it when a change would make older readers misparse a file.
     */
    public const int VERSION = 1;

    public const string FORMAT_LINE = '-- Format: ' . self::VERSION;

    public const string SCHEMA_VERSION_PREFIX = '-- Schema version: ';

    public const string FOOTER_PREFIX = '-- End of export: ';

    public const string FOOTER_SUFFIX = ' rows';

    public const string SET_NAMES = 'SET NAMES utf8mb4;';

    /**
     * Rows per INSERT statement, which also bounds the memory and the placeholders of an import.
     */
    public const int ROWS_PER_STATEMENT = 100;

    /**
     * Writes the header.
     *
     * @param string $schemaVersion Version of the last applied migration.
     * @param \DateTimeInterface $createdAt Creation date.
     * @return string
     */
    public static function header(string $schemaVersion, DateTimeInterface $createdAt): string
    {
        return implode("\n", [
            self::MAGIC,
            self::FORMAT_LINE,
            self::SCHEMA_VERSION_PREFIX . $schemaVersion,
            '-- Created: ' . $createdAt->format('c'),
            '-- Import it from the Admin page of the same schema version: it replaces the content of these tables.',
            self::SET_NAMES,
            '',
            '',
        ]);
    }

    /**
     * Writes the structure of a table.
     *
     * @param string $table Table name.
     * @param string $createStatement Statement returned by SHOW CREATE TABLE.
     * @return string
     */
    public static function structure(string $table, string $createStatement): string
    {
        return sprintf("DROP TABLE IF EXISTS `%s`;\n%s;\n\n", $table, $createStatement);
    }

    /**
     * Writes one INSERT statement.
     *
     * @param string $table Table name.
     * @param list<string> $columns Column names.
     * @param list<list<mixed>> $rows Values of each row, in the order of the columns.
     * @return string
     */
    public static function insert(string $table, array $columns, array $rows): string
    {
        $quotedColumns = array_map(fn(string $column): string => '`' . $column . '`', $columns);
        $rowLines = array_map(
            fn(array $row): string => '(' . implode(',', array_map(self::literal(...), $row)) . ')',
            $rows,
        );

        return sprintf(
            "INSERT INTO `%s` (%s) VALUES\n%s;\n\n",
            $table,
            implode(',', $quotedColumns),
            implode(",\n", $rowLines),
        );
    }

    /**
     * Writes the footer, whose absence tells the reader that the file is cut.
     *
     * @param int $rowCount Rows written in the whole file.
     * @return string
     */
    public static function footer(int $rowCount): string
    {
        return self::FOOTER_PREFIX . $rowCount . self::FOOTER_SUFFIX . "\n";
    }

    /**
     * Writes a value as an SQL literal.
     *
     * @param mixed $value Value read from the database.
     * @return string
     */
    private static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value) => (string)$value,
            is_bool($value) => $value ? '1' : '0',
            default => "'" . strtr((string)$value, [
                '\\' => '\\\\',
                "'" => "\\'",
                "\0" => '\\0',
                "\n" => '\\n',
                "\r" => '\\r',
                "\x1a" => '\\Z',
            ]) . "'",
        };
    }
}
