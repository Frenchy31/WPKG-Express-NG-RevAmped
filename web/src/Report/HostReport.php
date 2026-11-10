<?php

declare(strict_types=1);

namespace App\Report;

use DateTimeImmutable;

/**
 * Last run of one workstation, read from the files its WPKG_Client_Log package copies to the share.
 */
final readonly class HostReport
{
    /**
     * A report older than this did not come from today's run.
     */
    public const int STALE_AFTER_SECONDS = 86400;

    /**
     * Identifier of the package that installs the background agent, as in wpkg-agent/package.xml.
     */
    public const string AGENT_PACKAGE_ID = 'wpkg-agent';

    /**
     * @param string $hostname File name stem, i.e. %COMPUTERNAME% of the workstation.
     * @param \App\Report\RunLog|null $log Log of the last run, null without a .log file.
     * @param bool $hasXml Whether a .xml file exists.
     * @param array<string, string> $properties Attributes of the root element of the .xml file.
     * @param int|null $installedPackages Packages listed in the .xml file, null when it is missing or unreadable.
     * @param string|null $xmlError Why the .xml file could not be read.
     * @param \DateTimeImmutable $modifiedAt Last modification of the files.
     * @param list<string> $packageIds Identifiers of the packages listed in the .xml file.
     */
    public function __construct(
        public string $hostname,
        public ?RunLog $log,
        public bool $hasXml,
        public array $properties,
        public ?int $installedPackages,
        public ?string $xmlError,
        public DateTimeImmutable $modifiedAt,
        public array $packageIds = [],
    ) {
    }

    /**
     * Tells whether the background agent is installed on the workstation.
     *
     * @return bool
     */
    public function hasAgent(): bool
    {
        return in_array(self::AGENT_PACKAGE_ID, $this->packageIds, true);
    }

    /**
     * Tells how the last run ended.
     *
     * @return \App\Report\ReportStatus
     */
    public function status(): ReportStatus
    {
        return $this->log?->status() ?? ReportStatus::Unknown;
    }

    /**
     * Returns when the last run started, or when the files were written if the log does not tell.
     *
     * @return \DateTimeImmutable
     */
    public function ranAt(): DateTimeImmutable
    {
        return $this->log?->startedAt() ?? $this->modifiedAt;
    }

    /**
     * Tells whether the workstation missed its recent runs.
     *
     * @param \DateTimeImmutable $now Current time.
     * @return bool
     */
    public function isStale(DateTimeImmutable $now): bool
    {
        return $now->getTimestamp() - $this->ranAt()->getTimestamp() > self::STALE_AFTER_SECONDS;
    }

    /**
     * Returns the IP addresses wpkg.js found on the workstation.
     *
     * @return list<string>
     */
    public function ipAddresses(): array
    {
        $addresses = array_map('trim', explode(',', $this->properties['ipaddresses'] ?? ''));

        return array_values(array_filter($addresses, fn(string $address): bool => $address !== ''));
    }
}
