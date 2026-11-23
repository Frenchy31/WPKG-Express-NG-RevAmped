<?php

declare(strict_types=1);

namespace App\Cleanup;

/**
 * Tables checked for orphan rows. The order of the cases is the order of the cleanup: children first,
 * so that deleting a row never leaves a new orphan behind.
 */
enum OrphanTable: string
{
    case ExitCodes = 'exit_codes';
    case PackageActions = 'package_actions';
    case PackageChecks = 'package_checks';
    case Variables = 'variables';
    case HostsProfiles = 'hosts_profiles';
    case PackagesProfiles = 'packages_profiles';
    case PackagesPackages = 'packages_packages';
    case ProfilesProfiles = 'profiles_profiles';
    case Hosts = 'hosts';

    /**
     * Lists the columns that must point to an existing record.
     *
     * @return string
     */
    public function columns(): string
    {
        return match ($this) {
            self::ExitCodes => 'package_action_id',
            self::PackageActions => 'package_id',
            self::PackageChecks => 'package_id, parent_id',
            self::Variables => 'ref_id',
            self::HostsProfiles => 'host_id, profile_id',
            self::PackagesProfiles => 'package_id, profile_id',
            self::PackagesPackages => 'package_id, dependency_id',
            self::ProfilesProfiles => 'profile_id, dependency_id',
            self::Hosts => 'mainprofile_id',
        };
    }

    /**
     * Tells whether the cleanup deletes the orphan rows of the table. Hosts are only listed.
     *
     * @return bool
     */
    public function isCleaned(): bool
    {
        return $this !== self::Hosts;
    }
}
