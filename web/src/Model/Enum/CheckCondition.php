<?php

declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Value of package_checks.condition. The integers are stored in the database.
 */
enum CheckCondition: int
{
    case LogicalNot = 0;
    case LogicalAnd = 1;
    case LogicalOr = 2;
    case LogicalAtLeast = 3;
    case LogicalAtMost = 4;
    case RegistryExists = 5;
    case RegistryEquals = 6;
    case FileExists = 7;
    case FileSizeEquals = 8;
    case FileVersionSmallerThan = 9;
    case FileVersionLessOrEqual = 10;
    case FileVersionEqualTo = 11;
    case FileVersionGreaterThan = 12;
    case FileVersionGreaterOrEqual = 13;
    case ExecuteExitCodeSmallerThan = 14;
    case ExecuteExitCodeLessOrEqual = 15;
    case ExecuteExitCodeEqualTo = 16;
    case ExecuteExitCodeGreaterThan = 17;
    case ExecuteExitCodeGreaterOrEqual = 18;
    case UninstallExists = 19;
    case UninstallVersionSmallerThan = 20;
    case UninstallVersionLessOrEqual = 21;
    case UninstallVersionEqualTo = 22;
    case UninstallVersionGreaterThan = 23;
    case UninstallVersionGreaterOrEqual = 24;
    case HostHostname = 25;
    case HostOs = 26;
    case HostArchitecture = 27;
    case HostEnvironment = 28;

    /**
     * Returns the check type this condition applies to.
     *
     * @return \App\Model\Enum\CheckType
     */
    public function type(): CheckType
    {
        return match (true) {
            $this->value <= 4 => CheckType::Logical,
            $this->value <= 6 => CheckType::Registry,
            $this->value <= 13 => CheckType::File,
            $this->value <= 18 => CheckType::Execute,
            $this->value <= 24 => CheckType::Uninstall,
            default => CheckType::Host,
        };
    }

    /**
     * Returns the value of the `condition` attribute in packages.xml.
     *
     * @return string
     */
    public function xmlValue(): string
    {
        return match ($this) {
            self::LogicalNot => 'not',
            self::LogicalAnd => 'and',
            self::LogicalOr => 'or',
            self::LogicalAtLeast => 'atleast',
            self::LogicalAtMost => 'atmost',
            self::RegistryExists, self::FileExists, self::UninstallExists => 'exists',
            self::RegistryEquals => 'equals',
            self::FileSizeEquals => 'sizeequals',
            self::FileVersionSmallerThan, self::UninstallVersionSmallerThan => 'versionsmallerthan',
            self::FileVersionLessOrEqual, self::UninstallVersionLessOrEqual => 'versionlessorequal',
            self::FileVersionEqualTo, self::UninstallVersionEqualTo => 'versionequalto',
            self::FileVersionGreaterThan, self::UninstallVersionGreaterThan => 'versiongreaterthan',
            self::FileVersionGreaterOrEqual, self::UninstallVersionGreaterOrEqual => 'versiongreaterorequal',
            self::ExecuteExitCodeSmallerThan => 'exitcodesmallerthan',
            self::ExecuteExitCodeLessOrEqual => 'exitcodelessorequal',
            self::ExecuteExitCodeEqualTo => 'exitcodeequalto',
            self::ExecuteExitCodeGreaterThan => 'exitcodegreaterthan',
            self::ExecuteExitCodeGreaterOrEqual => 'exitcodegreaterorequal',
            self::HostHostname => 'hostname',
            self::HostOs => 'os',
            self::HostArchitecture => 'architecture',
            self::HostEnvironment => 'environment',
        };
    }

    /**
     * Tells whether the check needs a `value` attribute.
     *
     * @return bool
     */
    public function usesValue(): bool
    {
        return !in_array($this, [
            self::LogicalNot,
            self::LogicalAnd,
            self::LogicalOr,
            self::RegistryExists,
            self::FileExists,
            self::UninstallExists,
        ], true);
    }

    /**
     * Finds the condition matching a type and an XML attribute value.
     *
     * @param \App\Model\Enum\CheckType $type Check type.
     * @param string $xmlValue Attribute value.
     * @return self|null
     */
    public static function fromXml(CheckType $type, string $xmlValue): ?self
    {
        foreach ($type->conditions() as $condition) {
            if ($condition->xmlValue() === strtolower($xmlValue)) {
                return $condition;
            }
        }

        return null;
    }
}
