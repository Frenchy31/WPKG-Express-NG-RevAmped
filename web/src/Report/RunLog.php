<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Log file of one wpkg.js run, as written by getLogLine() in wpkg-client/wpkg.js.
 *
 * The client copies the file to the share while it still runs, so the log has no end marker.
 */
final class RunLog implements LogSummary
{
    /**
     * `2026-11-08 02:11:11, DEBUG   : message`
     */
    private const string LINE_PATTERN = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}), ([A-Z]+) *: ?(.*)$/';

    private const string PROFILES_PREFIX = 'Profiles applying to the current host:';

    /**
     * @param list<\App\Report\LogEntry> $entries Entries in file order.
     */
    private function __construct(private readonly array $entries)
    {
    }

    /**
     * Reads the content of a log file.
     *
     * wpkg.js writes in the ANSI code page of the workstation: Windows-1252 on French and
     * English systems.
     *
     * @param string $content File content.
     * @return self
     */
    public static function parse(string $content): self
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $entries = [];
        foreach ((array)preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = (string)$line;
            if ($line === '') {
                continue;
            }
            if (preg_match(self::LINE_PATTERN, $line, $matches)) {
                $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $matches[1]);
                $entries[] = new LogEntry($time ?: null, $matches[2], self::decodeMessage($matches[3]));
            } else {
                $entries[] = new LogEntry(null, null, $line);
            }
        }

        return new self($entries);
    }

    /**
     * Returns every entry in file order.
     *
     * @return list<\App\Report\LogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Returns the time of the "WPKG x starting..." entry, or of the first dated entry.
     *
     * @return \DateTimeImmutable|null
     */
    public function startedAt(): ?DateTimeImmutable
    {
        $firstTime = null;
        foreach ($this->entries as $entry) {
            if (str_starts_with($entry->message, 'WPKG ') && str_ends_with($entry->message, ' starting...')) {
                return $entry->time;
            }
            $firstTime ??= $entry->time;
        }

        return $firstTime;
    }

    /**
     * Returns the version of wpkg.js that wrote the log.
     *
     * @return string|null
     */
    public function wpkgVersion(): ?string
    {
        foreach ($this->entries as $entry) {
            if (preg_match('/^WPKG (\S+) starting\.\.\.$/', $entry->message, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Returns the profiles applied to the workstation.
     *
     * @return list<string>
     */
    public function profiles(): array
    {
        foreach ($this->entries as $entry) {
            if (str_starts_with($entry->message, self::PROFILES_PREFIX)) {
                $names = explode("\n", substr($entry->message, strlen(self::PROFILES_PREFIX)));

                return array_values(array_filter($names, fn(string $name): bool => $name !== ''));
            }
        }

        return [];
    }

    /**
     * Returns the messages of the package actions that succeeded.
     *
     * @return list<string>
     */
    public function succeeded(): array
    {
        $pattern = '/^(Processing \(\w+\) of .+ successful|Removal of .+ successful)\.$/';

        return $this->messages(
            fn(LogEntry $entry): bool => $entry->level === 'INFO' && preg_match($pattern, $entry->message) === 1,
        );
    }

    /**
     * Returns the messages of the ERROR entries.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->messages(fn(LogEntry $entry): bool => $entry->level === 'ERROR');
    }

    /**
     * Returns the messages of the WARNING entries.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->messages(fn(LogEntry $entry): bool => $entry->level === 'WARNING');
    }

    /**
     * Tells how the run ended.
     *
     * @return \App\Report\ReportStatus
     */
    public function status(): ReportStatus
    {
        $levels = array_map(fn(LogEntry $entry): ?string => $entry->level, $this->entries);

        return match (true) {
            in_array('ERROR', $levels, true) => ReportStatus::Failed,
            in_array('WARNING', $levels, true) => ReportStatus::Warning,
            array_filter($levels) !== [] => ReportStatus::Success,
            default => ReportStatus::Unknown,
        };
    }

    /**
     * Keeps what the pages show, without the entries.
     *
     * @return \App\Report\IndexedLog
     */
    public function summary(): IndexedLog
    {
        return new IndexedLog(
            $this->status(),
            $this->startedAt(),
            $this->wpkgVersion(),
            $this->profiles(),
            $this->succeeded(),
            $this->errors(),
            $this->warnings(),
        );
    }

    /**
     * Returns the messages of the entries accepted by a filter.
     *
     * @param callable(\App\Report\LogEntry): bool $filter Filter.
     * @return list<string>
     */
    private function messages(callable $filter): array
    {
        return array_values(array_map(
            fn(LogEntry $entry): string => $entry->message,
            array_filter($this->entries, $filter),
        ));
    }

    /**
     * Restores the line breaks wpkg.js writes as "|" and the pipes it writes as "\|".
     *
     * @param string $message Message as written in the file.
     * @return string
     */
    private static function decodeMessage(string $message): string
    {
        return rtrim(strtr($message, ['\\|' => '|', '|' => "\n"]), "\n");
    }
}
