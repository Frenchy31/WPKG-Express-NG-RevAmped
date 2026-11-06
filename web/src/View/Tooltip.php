<?php

declare(strict_types=1);

namespace App\View;

/**
 * Field descriptions shown as tooltips.
 */
final class Tooltip
{
    public const string PACKAGE_ENABLED = 'If enabled, this package will appear in package (xml) lists.';
    public const string PACKAGE_NAME =
        'Human-readable name that identifies this package (e.g. Foo Bar Baz Ultra Edition).';
    public const string PACKAGE_ID =
        'Unique ID containing only letters, numbers, underscores and hyphens (e.g. fooBarBaz_ultra OR '
        . 'foo-bar-baz-ultra).';
    public const string PACKAGE_REVISION =
        'An positive integer or positive decimal value that denotes the version/revision of this package '
        . '(e.g. 1 OR 1.0.3). If you have periods in the revision, you must have at least one digit after '
        . 'each period.';
    public const string PACKAGE_PRIORITY =
        'An integer value (any positive non-decimal number) that indicates this package\'s priority. '
        . 'Higher priorities take precedence over lower priorities (e.g. 10).';
    public const string PACKAGE_EXECUTE =
        '\'Always\' will always execute all of this package\'s Install actions every time, whereas '
        . '\'Once\' will only execute them once. Both of these modes will always ignore all Package checks.';
    public const string PACKAGE_REBOOT = 'If set, the system will reboot after installation of this package.';
    public const string PACKAGE_NOTIFY =
        'If set, the system will notify the user via \'net send\' when installation starts and stops.';
    public const string PACKAGE_NOTES = 'Additional notes about this package. Not used by WPKG.';
    public const string PACKAGE_LASTMODIFIED = 'The last date and time this package was modified.';
    public const string PACKAGE_DEPENDSON = 'Packages that this package depends on.';
    public const string PACKAGE_DEPENDEDONBY = 'Packages that depend on this package.';
    public const string PACKAGE_PROFILES = 'Profiles that contain this package.';
    public const string PACKAGECHECK_TYPE = 'The kind of check to be performed.';
    public const string PACKAGECHECK_CONDITION =
        'Describes how the package check is to be carried out for the selected type.';
    public const string PACKAGECHECK_PATH =
        'The contents of this field vary depending on the package check type and condition.';
    public const string PACKAGECHECK_VALUE =
        'The contents of this field vary depending on the package check type and condition.';
    public const string PACKAGECHECK_PARENT =
        'Determines where the package check should be placed in the package check hierarchy. All '
        . 'potential parents are listed in order.';
    public const string PACKAGEACTION_TYPE = 'The kind of action to be executed.';
    public const string PACKAGEACTION_COMMAND = 'The command to be executed.';
    public const string PACKAGEACTION_TIMEOUT =
        'The maximum number of seconds to wait for the action to execute. Default is 3600.';
    public const string PACKAGEACTION_WORKDIR = 'The working directory to use when executing this action.';
    public const string EXITCODE_CODE =
        'This is the expected exit code produced by the associated command/package action.';
    public const string EXITCODE_REBOOT =
        'Determines if and what kind of a reboot is performed when the specified exit code is detected.';
    public const string PROFILE_ENABLED = 'If enabled, this profile will appear in profile (xml) lists.';
    public const string PROFILE_ID =
        'Unique ID containing only letters, numbers, underscores and hyphens (e.g. base_software OR '
        . 'developer-suite OR complabsoft8).';
    public const string PROFILE_NOTES = 'Additional notes about this profile. Not used by WPKG.';
    public const string PROFILE_LASTMODIFIED = 'The last date and time this profile was modified.';
    public const string PROFILE_DEPENDSON = 'Profiles that this profile depends on.';
    public const string HOST_ENABLED = 'If enabled, this host will appear in profile (xml) lists.';
    public const string HOST_POSITION =
        'The position of this host entry. This is important for regular expression-based host names as '
        . 'they are evaluated in order. However, non-regular expression-based host names are ALWAYS checked '
        . 'first.';
    public const string HOST_NAME =
        'Either a regular expression or a literal host name. (e.g. libraryPC[0-9]+ OR principalcomp OR '
        . '.+).';
    public const string HOST_NOTES = 'Additional notes about this host. Not used by WPKG.';
    public const string HOST_LASTMODIFIED = 'The last date and time this host was modified.';
    public const string HOST_MAINPROFILE = 'The main profile that will always be evaluated for this host.';
}
