<?php

declare(strict_types=1);

namespace App\Report;

use Cake\Core\Configure;
use DateTimeImmutable;
use DirectoryIterator;
use DOMDocument;
use DOMElement;
use Generator;

/**
 * Directory of the share where workstations copy their wpkg.xml and log as <HOSTNAME>.xml and <HOSTNAME>.log.
 */
final class ReportDirectory
{
    /**
     * Also keeps any directory separator out of the file paths built from a host name.
     */
    private const string HOSTNAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    private const array XML_PROPERTIES = ['hostname', 'os', 'architecture', 'ipaddresses', 'domainname'];

    /**
     * @param string $path Directory, empty when the feature is not configured.
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Builds the directory from the application configuration.
     *
     * @return self
     */
    public static function fromConfiguration(): self
    {
        return new self((string)Configure::read('Wpkg.reportsPath'));
    }

    /**
     * Tells whether a directory is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->path !== '';
    }

    /**
     * Tells whether the configured directory can be listed.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->isConfigured() && is_dir($this->path) && is_readable($this->path);
    }

    /**
     * Lists the host names of the report files, one per file, one file at a time.
     *
     * A workstation with both files is listed twice.
     *
     * @return \Generator<int, string>
     */
    public function hostnames(): Generator
    {
        if (!$this->isReadable()) {
            return;
        }

        foreach (new DirectoryIterator($this->path) as $file) {
            if (!$file->isFile() || !in_array($file->getExtension(), ['log', 'xml'], true)) {
                continue;
            }
            $hostname = $file->getBasename('.' . $file->getExtension());
            if (preg_match(self::HOSTNAME_PATTERN, $hostname) === 1) {
                yield $hostname;
            }
        }
    }

    /**
     * Returns the path of the log file of a workstation.
     *
     * @param string $hostname Valid host name.
     * @return string
     */
    public function logPath(string $hostname): string
    {
        return $this->path . DS . $hostname . '.log';
    }

    /**
     * Returns the path of the wpkg.xml copy of a workstation.
     *
     * @param string $hostname Valid host name.
     * @return string
     */
    public function xmlPath(string $hostname): string
    {
        return $this->path . DS . $hostname . '.xml';
    }

    /**
     * Returns the report of one workstation.
     *
     * @param string $hostname Host name, as in the file names.
     * @return \App\Report\HostReport|null Null when the name is invalid or no file exists.
     */
    public function find(string $hostname): ?HostReport
    {
        if (!$this->isConfigured() || preg_match(self::HOSTNAME_PATTERN, $hostname) !== 1) {
            return null;
        }

        $logPath = $this->logPath($hostname);
        $xmlPath = $this->xmlPath($hostname);
        $existing = array_values(array_filter([$logPath, $xmlPath], 'is_file'));
        if ($existing === []) {
            return null;
        }

        [$log, $logError] = is_file($logPath) ? $this->readLog($logPath) : [null, null];
        [$properties, $packageIds, $xmlError] = is_file($xmlPath) ? $this->readXml($xmlPath) : [[], null, null];
        $modifiedAt = (new DateTimeImmutable())->setTimestamp(max(array_map('filemtime', $existing)));

        return new HostReport(
            $hostname,
            $log,
            is_file($xmlPath),
            $properties,
            $packageIds === null ? null : count($packageIds),
            $xmlError,
            $modifiedAt,
            $logError,
            $packageIds ?? [],
        );
    }

    /**
     * Reads a log file.
     *
     * @param string $logPath File path.
     * @return array{0: \App\Report\RunLog, 1: string|null} Log, error.
     */
    public function readLog(string $logPath): array
    {
        $content = @file_get_contents($logPath);
        if ($content === false) {
            return [RunLog::parse(''), __('The file cannot be read.')];
        }

        return [RunLog::parse($content), trim($content) === '' ? __('The file is empty.') : null];
    }

    /**
     * Reads the host properties and the installed packages of a wpkg.xml file.
     *
     * @param string $xmlPath File path.
     * @return array{0: array<string, string>, 1: list<string>|null, 2: string|null} Properties, package
     *     identifiers, error.
     */
    public function readXml(string $xmlPath): array
    {
        $content = (string)@file_get_contents($xmlPath);
        if (trim($content) === '') {
            return [[], null, __('The file is empty.')];
        }

        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument();
        $isLoaded = $document->loadXML($content, LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        $root = $document->documentElement;
        if (!$isLoaded || $root === null) {
            $error = $errors === [] ? __('The file is not well-formed.') : __(
                '{0} (line {1})',
                trim($errors[0]->message),
                (string)$errors[0]->line,
            );

            return [[], null, $error];
        }

        $properties = [];
        foreach (self::XML_PROPERTIES as $name) {
            if ($root->hasAttribute($name)) {
                $properties[$name] = $root->getAttribute($name);
            }
        }
        $packageIds = [];
        foreach ($root->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'package') {
                $packageIds[] = $node->getAttribute('id');
            }
        }

        return [$properties, $packageIds, null];
    }
}
