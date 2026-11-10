<?php

declare(strict_types=1);

namespace App\Report;

use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeImmutable;

/**
 * Keeps the host_reports table in line with the report files of the share.
 *
 * Files are handled one at a time and only the summary is kept, so a pass uses the same memory
 * whatever the number of workstations. A file is read again only when its modification date or its
 * size differs from the recorded ones.
 */
final class ReportIndexer
{
    use LocatorAwareTrait;

    private const int SWEEP_PAGE_SIZE = 200;

    private const int VERSION_LENGTH = 50;

    /**
     * @param \App\Report\ReportDirectory $directory Directory of the share.
     */
    public function __construct(private readonly ReportDirectory $directory)
    {
    }

    /**
     * Runs one pass and records its result.
     *
     * @return \App\Report\IndexState
     */
    public function run(): IndexState
    {
        clearstatcache();
        $startedAt = hrtime(true);
        $filesSeen = 0;
        $filesRead = 0;
        $hostsRemoved = 0;
        $error = null;

        if ($this->directory->isReadable()) {
            foreach ($this->directory->hostnames() as $hostname) {
                $filesSeen++;
                $filesRead += $this->index($hostname);
            }
            $hostsRemoved = $this->removeVanished();
        } else {
            $error = __('Reports directory cannot be read');
        }

        $state = new IndexState(
            new DateTimeImmutable(),
            intdiv((int)(hrtime(true) - $startedAt), 1_000_000),
            $filesSeen,
            $filesRead,
            $hostsRemoved,
            $error,
        );
        $this->fetchTable('ReportIndexState')->record($state);

        return $state;
    }

    /**
     * Brings the row of a workstation up to date.
     *
     * @param string $hostname Valid host name.
     * @return int Number of files read.
     */
    private function index(string $hostname): int
    {
        $logPath = $this->directory->logPath($hostname);
        $xmlPath = $this->directory->xmlPath($hostname);
        $log = $this->statFile($logPath);
        $xml = $this->statFile($xmlPath);
        if ($log === null && $xml === null) {
            return 0;
        }

        $table = $this->fetchTable('HostReports');
        $recorded = $table->recordedFiles($hostname);
        $isLogChanged = $this->isChanged($recorded, 'log', $log);
        $isXmlChanged = $this->isChanged($recorded, 'xml', $xml);
        if ($recorded !== null && !$isLogChanged && !$isXmlChanged) {
            return 0;
        }

        $values = $recorded === null ? $this->emptyLog() + $this->emptyXml() : [];
        $filesRead = 0;
        if ($isLogChanged) {
            $values = ($log === null ? $this->emptyLog() : $this->logValues($logPath, $log)) + $values;
            $filesRead += (int)($log !== null);
        }
        if ($isXmlChanged) {
            $values = ($xml === null ? $this->emptyXml() : $this->xmlValues($xmlPath, $xml)) + $values;
            $filesRead += (int)($xml !== null);
        }
        $values['file_modified_at'] = max(array_column(array_filter([$log, $xml]), 'mtime'));
        $values['indexed_at'] = time();
        $table->store($hostname, $values, $recorded !== null);

        return $filesRead;
    }

    /**
     * Deletes the rows of the workstations whose two files are gone.
     *
     * @return int Number of rows deleted.
     */
    private function removeVanished(): int
    {
        $table = $this->fetchTable('HostReports');
        $removed = 0;
        $after = '';
        do {
            $hostnames = $table->hostnamesAfter($after, self::SWEEP_PAGE_SIZE);
            foreach ($hostnames as $hostname) {
                if (!is_file($this->directory->logPath($hostname)) && !is_file($this->directory->xmlPath($hostname))) {
                    $table->forget($hostname);
                    $removed++;
                }
            }
            $after = $hostnames === [] ? '' : $hostnames[array_key_last($hostnames)];
        } while (count($hostnames) === self::SWEEP_PAGE_SIZE);

        return $removed;
    }

    /**
     * Reads the modification date and the size of a file.
     *
     * @param string $path File path.
     * @return array{mtime: int, size: int}|null Null when the file does not exist.
     */
    private function statFile(string $path): ?array
    {
        $info = is_file($path) ? @stat($path) : false;

        return $info === false ? null : ['mtime' => $info['mtime'], 'size' => $info['size']];
    }

    /**
     * Tells whether a file differs from what the row recorded.
     *
     * @param array<string, int|null>|null $recorded Recorded values, null without a row.
     * @param string $prefix Column prefix, log or xml.
     * @param array{mtime: int, size: int}|null $current Current values, null without a file.
     * @return bool
     */
    private function isChanged(?array $recorded, string $prefix, ?array $current): bool
    {
        if ($recorded === null) {
            return $current !== null;
        }

        return ($current['mtime'] ?? null) !== $recorded[$prefix . '_mtime']
            || ($current['size'] ?? null) !== $recorded[$prefix . '_size'];
    }

    /**
     * Returns the columns of a workstation without a log file.
     *
     * @return array<string, mixed>
     */
    private function emptyLog(): array
    {
        return [
            'log_mtime' => null,
            'log_size' => null,
            'status' => ReportStatus::Unknown->value,
            'started_at' => null,
            'wpkg_version' => null,
            'profiles' => '[]',
            'errors' => '[]',
            'warnings' => '[]',
            'succeeded' => '[]',
            'log_error' => null,
        ];
    }

    /**
     * Returns the columns of a workstation without a wpkg.xml copy.
     *
     * @return array<string, mixed>
     */
    private function emptyXml(): array
    {
        return [
            'xml_mtime' => null,
            'xml_size' => null,
            'has_xml' => false,
            'xml_error' => null,
            'properties' => '{}',
            'installed_packages' => null,
            'package_ids' => '[]',
        ];
    }

    /**
     * Reads a log file and returns its columns.
     *
     * @param string $logPath File path.
     * @param array{mtime: int, size: int} $file Date and size taken before the read.
     * @return array<string, mixed>
     */
    private function logValues(string $logPath, array $file): array
    {
        [$log, $logError] = $this->directory->readLog($logPath);
        $summary = $log->summary();
        unset($log);

        return [
            'log_mtime' => $file['mtime'],
            'log_size' => $file['size'],
            'status' => $summary->status()->value,
            'started_at' => $summary->startedAt()?->getTimestamp(),
            'wpkg_version' => $summary->wpkgVersion() === null
                ? null
                : mb_substr($summary->wpkgVersion(), 0, self::VERSION_LENGTH),
            'profiles' => $this->encode($summary->profiles()),
            'errors' => $this->encode($summary->errors()),
            'warnings' => $this->encode($summary->warnings()),
            'succeeded' => $this->encode($summary->succeeded()),
            'log_error' => $logError,
        ];
    }

    /**
     * Reads a wpkg.xml copy and returns its columns.
     *
     * @param string $xmlPath File path.
     * @param array{mtime: int, size: int} $file Date and size taken before the read.
     * @return array<string, mixed>
     */
    private function xmlValues(string $xmlPath, array $file): array
    {
        [$properties, $packageIds, $xmlError] = $this->directory->readXml($xmlPath);

        return [
            'xml_mtime' => $file['mtime'],
            'xml_size' => $file['size'],
            'has_xml' => true,
            'xml_error' => $xmlError,
            'properties' => $this->encode($properties),
            'installed_packages' => $packageIds === null ? null : count($packageIds),
            'package_ids' => $this->encode($packageIds ?? []),
        ];
    }

    /**
     * Encodes a list or a map for a JSON text column.
     *
     * @param array<array-key, string> $values Values.
     * @return string
     */
    private function encode(array $values): string
    {
        return (string)json_encode($values, JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
