<?php

declare(strict_types=1);

namespace App\Report;

use Cake\Core\Configure;
use DateTimeImmutable;
use DirectoryIterator;
use DOMDocument;
use DOMElement;

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
     * Returns the report of every workstation found in the directory.
     *
     * @return list<\App\Report\HostReport>
     */
    public function all(): array
    {
        if (!$this->isReadable()) {
            return [];
        }

        $hostnames = [];
        foreach (new DirectoryIterator($this->path) as $file) {
            if (!$file->isFile() || !in_array($file->getExtension(), ['log', 'xml'], true)) {
                continue;
            }
            $hostname = $file->getBasename('.' . $file->getExtension());
            if (preg_match(self::HOSTNAME_PATTERN, $hostname) === 1) {
                $hostnames[$hostname] = true;
            }
        }

        $reports = [];
        foreach (array_keys($hostnames) as $hostname) {
            $report = $this->find((string)$hostname);
            if ($report !== null) {
                $reports[] = $report;
            }
        }

        return $reports;
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

        $logPath = $this->path . DS . $hostname . '.log';
        $xmlPath = $this->path . DS . $hostname . '.xml';
        $existing = array_values(array_filter([$logPath, $xmlPath], 'is_file'));
        if ($existing === []) {
            return null;
        }

        $log = is_file($logPath) ? RunLog::parse((string)file_get_contents($logPath)) : null;
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
            $packageIds ?? [],
        );
    }

    /**
     * Reads the host properties and the installed packages of a wpkg.xml file.
     *
     * @param string $xmlPath File path.
     * @return array{0: array<string, string>, 1: list<string>|null, 2: string|null} Properties, package
     *     identifiers, error.
     */
    private function readXml(string $xmlPath): array
    {
        $content = (string)file_get_contents($xmlPath);
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
