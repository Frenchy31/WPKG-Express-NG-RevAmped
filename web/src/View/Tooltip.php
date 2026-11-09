<?php

declare(strict_types=1);

namespace App\View;

/**
 * Field descriptions shown as tooltips.
 */
final class Tooltip
{
    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageEnabled(): string
    {
        return __('If enabled, this package will appear in package (xml) lists.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageName(): string
    {
        return __('Human-readable name that identifies this package (e.g. Foo Bar Baz Ultra Edition).');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageId(): string
    {
        return __(
            'Unique ID containing only letters, numbers, underscores and hyphens (e.g. fooBarBaz_ultra OR '
            . 'foo-bar-baz-ultra).',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageRevision(): string
    {
        return __(
            'An positive integer or positive decimal value that denotes the version/revision of this package '
            . '(e.g. 1 OR 1.0.3). If you have periods in the revision, you must have at least one digit after '
            . 'each period.',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagePriority(): string
    {
        return __(
            'An integer value (any positive non-decimal number) that indicates this package\'s priority. '
            . 'Higher priorities take precedence over lower priorities (e.g. 10).',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageExecute(): string
    {
        return __(
            '\'Always\' will always execute all of this package\'s Install actions every time, whereas '
            . '\'Once\' will only execute them once. Both of these modes will always ignore all Package checks.',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageReboot(): string
    {
        return __('If set, the system will reboot after installation of this package.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageNotify(): string
    {
        return __('If set, the system will notify the user via \'net send\' when installation starts and stops.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageNotes(): string
    {
        return __('Additional notes about this package. Not used by WPKG.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageLastmodified(): string
    {
        return __('The last date and time this package was modified.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageDependson(): string
    {
        return __('Packages that this package depends on.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageDependedonby(): string
    {
        return __('Packages that depend on this package.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageProfiles(): string
    {
        return __('Profiles that contain this package.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagecheckType(): string
    {
        return __('The kind of check to be performed.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagecheckCondition(): string
    {
        return __('Describes how the package check is to be carried out for the selected type.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagecheckPath(): string
    {
        return __('The contents of this field vary depending on the package check type and condition.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagecheckValue(): string
    {
        return __('The contents of this field vary depending on the package check type and condition.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packagecheckParent(): string
    {
        return __(
            'Determines where the package check should be placed in the package check hierarchy. All '
            . 'potential parents are listed in order.',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageactionType(): string
    {
        return __('The kind of action to be executed.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageactionCommand(): string
    {
        return __('The command to be executed.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageactionTimeout(): string
    {
        return __('The maximum number of seconds to wait for the action to execute. Default is 3600.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function packageactionWorkdir(): string
    {
        return __('The working directory to use when executing this action.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function exitcodeCode(): string
    {
        return __('This is the expected exit code produced by the associated command/package action.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function exitcodeReboot(): string
    {
        return __('Determines if and what kind of a reboot is performed when the specified exit code is detected.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function profileEnabled(): string
    {
        return __('If enabled, this profile will appear in profile (xml) lists.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function profileId(): string
    {
        return __(
            'Unique ID containing only letters, numbers, underscores and hyphens (e.g. base_software OR '
            . 'developer-suite OR complabsoft8).',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function profileNotes(): string
    {
        return __('Additional notes about this profile. Not used by WPKG.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function profileLastmodified(): string
    {
        return __('The last date and time this profile was modified.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function profileDependson(): string
    {
        return __('Profiles that this profile depends on.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostEnabled(): string
    {
        return __('If enabled, this host will appear in profile (xml) lists.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostPosition(): string
    {
        return __(
            'The position of this host entry. This is important for regular expression-based host names as '
            . 'they are evaluated in order. However, non-regular expression-based host names are ALWAYS checked '
            . 'first.',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostName(): string
    {
        return __(
            'Either a regular expression or a literal host name. (e.g. libraryPC[0-9]+ OR principalcomp OR '
            . '.+).',
        );
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostNotes(): string
    {
        return __('Additional notes about this host. Not used by WPKG.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostLastmodified(): string
    {
        return __('The last date and time this host was modified.');
    }

    /**
     * Tooltip text.
     *
     * @return string
     */
    public static function hostMainprofile(): string
    {
        return __('The main profile that will always be evaluated for this host.');
    }
}
