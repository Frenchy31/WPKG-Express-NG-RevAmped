<?php

declare(strict_types=1);

namespace App\Report;

use App\Model\Entity\Host;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Finds the host entry of a workstation the way getHostsApplying() does in wpkg-client/wpkg.js.
 *
 * Entries are tried in hosts.xml order, the first match wins:
 * 1. same name, ignoring case;
 * 2. IP range such as 192.168.1-20.5, against the addresses of the workstation;
 * 3. regular expression ^name$, ignoring case.
 */
final class HostMatcher
{
    use LocatorAwareTrait;

    /**
     * @var list<\App\Model\Entity\Host>|null
     */
    private ?array $hosts = null;

    /**
     * Returns the first host entry matching a workstation.
     *
     * @param string $hostname Host name of the workstation.
     * @param list<string> $ipAddresses IP addresses of the workstation.
     * @return \App\Model\Entity\Host|null
     */
    public function match(string $hostname, array $ipAddresses): ?Host
    {
        foreach ($this->hosts() as $host) {
            if ($this->matches((string)$host->get('name'), $hostname, $ipAddresses)) {
                return $host;
            }
        }

        return null;
    }

    /**
     * Groups reports by matching host entry, in hosts.xml order, unmatched workstations last.
     *
     * @param list<\App\Report\HostReport> $reports Reports.
     * @return list<\App\Report\ReportGroup>
     */
    public function group(array $reports): array
    {
        $reportsByHost = [];
        $unmatched = [];
        foreach ($reports as $report) {
            $host = $this->match($report->hostname, $report->ipAddresses());
            if ($host === null) {
                $unmatched[] = $report;
            } else {
                $reportsByHost[$host->get('id')][] = $report;
            }
        }

        $groups = [];
        foreach ($this->hosts() as $host) {
            if (isset($reportsByHost[$host->get('id')])) {
                $groups[] = new ReportGroup($host, $this->sorted($reportsByHost[$host->get('id')]));
            }
        }
        if ($unmatched !== []) {
            $groups[] = new ReportGroup(null, $this->sorted($unmatched));
        }

        return $groups;
    }

    /**
     * Returns the host entries hosts.xml exposes, in feed order.
     *
     * @return list<\App\Model\Entity\Host>
     */
    private function hosts(): array
    {
        return $this->hosts ??= $this->fetchTable('Hosts')->find('forFeed')->all()->toList();
    }

    /**
     * Tells whether a host entry applies to a workstation.
     *
     * @param string $pattern Name of the host entry.
     * @param string $hostname Host name of the workstation.
     * @param list<string> $ipAddresses IP addresses of the workstation.
     * @return bool
     */
    private function matches(string $pattern, string $hostname, array $ipAddresses): bool
    {
        if ($pattern === '') {
            return false;
        }
        if (strtoupper($pattern) === strtoupper($hostname)) {
            return true;
        }
        foreach ($ipAddresses as $ipAddress) {
            if ($this->matchesIpRange($pattern, $ipAddress)) {
                return true;
            }
        }

        // An invalid expression makes preg_match() return false: wpkg.js skips the entry too.
        return @preg_match('/^' . str_replace('/', '\/', $pattern) . '$/i', $hostname) === 1;
    }

    /**
     * Tells whether an address falls in a range such as 192.168.1-20.5.
     *
     * @param string $pattern Name of the host entry.
     * @param string $ipAddress IPv4 address.
     * @return bool
     */
    private function matchesIpRange(string $pattern, string $ipAddress): bool
    {
        $ipOctets = explode('.', $ipAddress);
        $patternOctets = explode('.', $pattern);
        if (count($ipOctets) !== 4 || count($patternOctets) !== 4) {
            return false;
        }

        foreach ($ipOctets as $index => $ipOctet) {
            $bounds = explode('-', $patternOctets[$index]);
            $first = self::parseInt($bounds[0]);
            $second = count($bounds) > 1 ? self::parseInt($bounds[1]) : $first;
            if ($first !== null && $second !== null && $first > $second) {
                [$first, $second] = [$second, $first];
            }
            $octet = self::parseInt($ipOctet);
            // wpkg.js compares with NaN when a bound is not a number, which never rejects the address.
            $isBelow = $octet !== null && $first !== null && $octet < $first;
            $isAbove = $octet !== null && $second !== null && $octet > $second;
            if ($isBelow || $isAbove) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads the leading integer of a string like parseInt() in JavaScript.
     *
     * @param string $value Value.
     * @return int|null Null where JavaScript returns NaN.
     */
    private static function parseInt(string $value): ?int
    {
        return preg_match('/^\s*[+-]?\d+/', $value, $matches) === 1 ? (int)$matches[0] : null;
    }

    /**
     * Sorts reports by host name.
     *
     * @param list<\App\Report\HostReport> $reports Reports.
     * @return list<\App\Report\HostReport>
     */
    private function sorted(array $reports): array
    {
        usort(
            $reports,
            fn(HostReport $left, HostReport $right): int => strnatcasecmp($left->hostname, $right->hostname),
        );

        return $reports;
    }
}
