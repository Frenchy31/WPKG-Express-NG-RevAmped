<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Demonstration fleet: two computer rooms, teachers' laptops, common software.
 *
 *   bin/cake seeds run DemoSeed
 *
 * Only for an empty database: the seed stops when a package, profile or host already exists.
 * The integer columns hold the values of src/Model/Enum (ActionType, CheckType, CheckCondition,
 * ExitCodeReboot, PackageExecute, PackageReboot, VariableType).
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace -- the Migrations plugin resolves classes by file name.
class DemoSeed extends BaseSeed
{
    private const string CREATED = '2026-09-01 08:00:00';

    /**
     * Inserts the demonstration fleet.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (['packages', 'profiles', 'hosts'] as $tableName) {
            $existing = $this->fetchRow('SELECT COUNT(*) AS total FROM ' . $tableName);
            if ($existing !== false && (int)$existing['total'] > 0) {
                $this->getIo()?->warning("Table {$tableName} is not empty: DemoSeed only fills an empty database.");

                return;
            }
        }

        $this->insertProfiles();
        $this->insertPackages();
        $this->insertPackageActions();
        $this->insertPackageChecks();
        $this->insertHosts();
        $this->insertVariables();
    }

    /**
     * Inserts the profiles, their dependencies and their packages.
     *
     * @return void
     */
    private function insertProfiles(): void
    {
        $this->insert('profiles', [
            $this->profile(1, 'base', 'Socle commun à tous les postes'),
            $this->profile(2, 'room1', 'Salle 1 : bureautique'),
            $this->profile(3, 'room2', 'Salle 2 : développement'),
            $this->profile(4, 'nomades', 'Portables des enseignants, mis à jour en arrière-plan'),
            $this->profile(5, 'retired', 'Ancien profil, conservé pour mémoire', false),
        ]);
        $this->insert('profiles_profiles', [
            ['profile_id' => 2, 'dependency_id' => 1],
            ['profile_id' => 3, 'dependency_id' => 1],
            ['profile_id' => 4, 'dependency_id' => 1],
        ]);

        $packageIdsByProfileId = [
            1 => [1, 2, 3, 4, 9],
            2 => [5],
            3 => [5, 6, 7],
            4 => [5, 8],
            5 => [10],
        ];
        $profilePackages = [];
        foreach ($packageIdsByProfileId as $profileId => $packageIds) {
            foreach ($packageIds as $packageId) {
                $profilePackages[] = ['profile_id' => $profileId, 'package_id' => $packageId];
            }
        }
        $this->insert('packages_profiles', $profilePackages);
    }

    /**
     * Inserts the packages and their dependencies.
     *
     * @return void
     */
    private function insertPackages(): void
    {
        $this->insert('packages', [
            $this->package(1, '7-Zip', '7zip', '24.08', 50),
            $this->package(2, 'Mozilla Firefox ESR', 'firefox', '128.5.0', 40),
            $this->package(3, 'VLC media player', 'vlc', '3.0.21', 30),
            $this->package(4, 'Notepad++', 'notepadpp', '8.7.1', 30),
            $this->package(5, 'LibreOffice', 'libreoffice', '24.8.3', 20, reboot: 2, notes: 'Redémarrage reporté'),
            $this->package(6, 'Python', 'python', '3.12.7', 20),
            $this->package(7, 'Visual Studio Code', 'vscode', '1.95.3', 10),
            $this->package(8, 'WPKG background agent', 'wpkg-agent', '1', 100, notes: 'docs/wpkg-agent-package.md'),
            $this->package(
                9,
                'WPKG_Client_Log',
                'WPKG_Client_Log',
                '1',
                0,
                execute: 1,
                notes: 'Copie wpkg.xml et le journal sur le partage pour la page Reports',
            ),
            $this->package(
                10,
                'Adobe Flash Player',
                'flashplayer',
                '32.0.0.465',
                10,
                enabled: false,
                notes: 'Fin de support : retiré du parc',
            ),
        ]);
        $this->insert('packages_packages', [
            ['package_id' => 7, 'dependency_id' => 6],
        ]);
    }

    /**
     * Inserts the install, upgrade and remove commands, and the exit codes of LibreOffice.
     *
     * @return void
     */
    private function insertPackageActions(): void
    {
        $agentScript = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%AGENT_SOURCE%\\';
        $agentInstall = $agentScript . 'install.ps1" -ServerUrl %WPKG_SERVER%';
        $copyToLogs = '%COMSPEC% /C if exist C:\admin\\';
        $logsPath = ' %SOFTWARE%\..\logs\%COMPUTERNAME%';
        $runLog = 'wpkg-%COMPUTERNAME%.log';
        $commandsByPackageId = [
            1 => [
                [0, '"%SOFTWARE%\7zip\7z2408-x64.exe" /S'],
                [1, '"%SOFTWARE%\7zip\7z2408-x64.exe" /S'],
                [3, '"%ProgramFiles%\7-Zip\Uninstall.exe" /S'],
            ],
            2 => [
                [0, '"%SOFTWARE%\firefox\Firefox Setup 128.5.0esr.exe" /S'],
                [1, '"%SOFTWARE%\firefox\Firefox Setup 128.5.0esr.exe" /S'],
                [3, '"%ProgramFiles%\Mozilla Firefox\uninstall\helper.exe" /S'],
            ],
            3 => [
                [0, '"%SOFTWARE%\vlc\vlc-3.0.21-win64.exe" /L=1036 /S'],
                [1, '"%SOFTWARE%\vlc\vlc-3.0.21-win64.exe" /L=1036 /S'],
                [3, '"%ProgramFiles%\VideoLAN\VLC\uninstall.exe" /S'],
            ],
            4 => [
                [0, '"%SOFTWARE%\notepadpp\npp.8.7.1.Installer.x64.exe" /S'],
                [1, '"%SOFTWARE%\notepadpp\npp.8.7.1.Installer.x64.exe" /S'],
                [3, '"%ProgramFiles%\Notepad++\uninstall.exe" /S'],
            ],
            5 => [
                [0, 'msiexec /i "%SOFTWARE%\libreoffice\LibreOffice_24.8.3_Win_x86-64.msi" /qn /norestart'],
                [1, 'msiexec /i "%SOFTWARE%\libreoffice\LibreOffice_24.8.3_Win_x86-64.msi" /qn /norestart'],
                [3, 'msiexec /x "%SOFTWARE%\libreoffice\LibreOffice_24.8.3_Win_x86-64.msi" /qn /norestart'],
            ],
            6 => [
                [0, '"%SOFTWARE%\python\python-3.12.7-amd64.exe" /quiet InstallAllUsers=1 PrependPath=1'],
                [1, '"%SOFTWARE%\python\python-3.12.7-amd64.exe" /quiet InstallAllUsers=1 PrependPath=1'],
                [3, '"%SOFTWARE%\python\python-3.12.7-amd64.exe" /quiet /uninstall'],
            ],
            7 => [
                [0, '"%SOFTWARE%\vscode\VSCodeSetup-x64-1.95.3.exe" /VERYSILENT /NORESTART /MERGETASKS=!runcode'],
                [1, '"%SOFTWARE%\vscode\VSCodeSetup-x64-1.95.3.exe" /VERYSILENT /NORESTART /MERGETASKS=!runcode'],
                [3, '"%ProgramFiles%\Microsoft VS Code\unins000.exe" /VERYSILENT'],
            ],
            8 => [
                [0, $agentInstall],
                [1, $agentInstall],
                [3, $agentScript . 'uninstall.ps1"'],
            ],
            9 => [
                [0, $copyToLogs . 'wpkg.xml copy /Y C:\admin\wpkg.xml' . $logsPath . '.xml'],
                [0, $copyToLogs . $runLog . ' copy /Y C:\admin\\' . $runLog . $logsPath . '.log'],
            ],
            10 => [
                [0, '"%SOFTWARE%\flash\install_flash_player_32_active_x.exe" -install'],
                [3, '"%SOFTWARE%\flash\uninstall_flash_player.exe" -uninstall'],
            ],
        ];

        $actions = [];
        $actionId = 0;
        foreach ($commandsByPackageId as $packageId => $commands) {
            foreach ($commands as $position => [$actionType, $command]) {
                $actions[] = [
                    'id' => ++$actionId,
                    'package_id' => $packageId,
                    'type' => $actionType,
                    'command' => $command,
                    'timeout' => $packageId === 5 ? 1800 : null,
                    'workdir' => null,
                    'position' => $position + 1,
                ];
            }
        }
        $this->insert('package_actions', $actions);

        $libreOfficeInstallId = 13;
        $this->insert('exit_codes', [
            ['package_action_id' => $libreOfficeInstallId, 'code' => '0', 'reboot' => 0],
            ['package_action_id' => $libreOfficeInstallId, 'code' => '3010', 'reboot' => 3],
            ['package_action_id' => $libreOfficeInstallId, 'code' => '1641', 'reboot' => 3],
        ]);
    }

    /**
     * Inserts one check per package, each in its own range of the global nested set.
     *
     * @return void
     */
    private function insertPackageChecks(): void
    {
        $checks = [
            [1, 3, 24, '7-Zip.*', '24.08'],
            [2, 3, 24, 'Mozilla Firefox.*ESR.*', '128.5.0'],
            [3, 3, 24, 'VLC media player', '3.0.21'],
            [4, 3, 24, 'Notepad\+\+.*', '8.7.1'],
            [5, 3, 24, 'LibreOffice 24.*', '24.8.3'],
            [6, 3, 24, 'Python 3\.12\..*', '3.12.7'],
            [7, 3, 24, 'Microsoft Visual Studio Code.*', '1.95.3'],
            [8, 2, 7, '%ProgramData%\WpkgAgent\run.ps1', null],
            [10, 3, 19, 'Adobe Flash Player.*', null],
        ];

        $rows = [];
        foreach ($checks as $index => [$packageId, $checkType, $condition, $path, $value]) {
            $rows[] = [
                'package_id' => $packageId,
                'type' => $checkType,
                'condition' => $condition,
                'path' => $path,
                'value' => $value,
                'parent_id' => null,
                'lft' => 2 * $index + 1,
                'rght' => 2 * $index + 2,
            ];
        }
        $this->insert('package_checks', $rows);
    }

    /**
     * Inserts the host entries, in the order wpkg.js reads them.
     *
     * @return void
     */
    private function insertHosts(): void
    {
        $this->insert('hosts', [
            $this->host(1, 'room1-pc.+', 2, 1, 'Salle 1, 16 postes'),
            $this->host(2, 'room2-pc.+', 3, 2, 'Salle 2, 12 postes'),
            $this->host(3, 'laptop-4[0-9]', 4, 3, 'Chariot de portables'),
            $this->host(4, 'LAPTOP-12', 4, 4, 'Portable de la vie scolaire'),
            $this->host(5, 'LAPTOP-31', 4, 5, 'Portable du CDI'),
            $this->host(6, 'old-pc.+', 1, 6, 'Anciens postes, sortis du parc', false),
        ]);
    }

    /**
     * Inserts the variables of packages, profiles and hosts.
     *
     * @return void
     */
    private function insertVariables(): void
    {
        $this->insert('variables', [
            ['ref_id' => 1, 'ref_type' => 1, 'name' => 'SOFTWARE', 'value' => '\\\\172.19.0.4\WPKG\wpkg\packages'],
            ['ref_id' => 8, 'ref_type' => 0, 'name' => 'AGENT_SOURCE', 'value' => '\\\\172.19.0.4\WPKG\wpkg\agent'],
            ['ref_id' => 8, 'ref_type' => 0, 'name' => 'WPKG_SERVER', 'value' => 'https://wpkg.example.org'],
            ['ref_id' => 1, 'ref_type' => 2, 'name' => 'ROOM', 'value' => 'room1'],
            ['ref_id' => 2, 'ref_type' => 2, 'name' => 'ROOM', 'value' => 'room2'],
        ]);
    }

    /**
     * Builds a profiles row.
     *
     * @param int $profileId Identifier.
     * @param string $idText Name used in the XML feeds.
     * @param string $notes Notes.
     * @param bool $enabled Whether the profile is enabled.
     * @return array<string, mixed>
     */
    private function profile(int $profileId, string $idText, string $notes, bool $enabled = true): array
    {
        return [
            'id' => $profileId,
            'enabled' => $enabled ? 1 : 0,
            'id_text' => $idText,
            'notes' => $notes,
            'created' => self::CREATED,
            'modified' => self::CREATED,
        ];
    }

    /**
     * Builds a packages row.
     *
     * @param int $packageId Identifier.
     * @param string $name Display name.
     * @param string $idText Name used in the XML feeds.
     * @param string $revision Revision: a change makes the workstations upgrade.
     * @param int $priority Higher runs first.
     * @param int $reboot PackageReboot value.
     * @param int $execute PackageExecute value.
     * @param bool $enabled Whether the package is enabled.
     * @param string|null $notes Notes.
     * @return array<string, mixed>
     */
    private function package(
        int $packageId,
        string $name,
        string $idText,
        string $revision,
        int $priority,
        int $reboot = 0,
        int $execute = 0,
        bool $enabled = true,
        ?string $notes = null,
    ): array {
        return [
            'id' => $packageId,
            'name' => $name,
            'id_text' => $idText,
            'enabled' => $enabled ? 1 : 0,
            'revision' => $revision,
            'priority' => $priority,
            'reboot' => $reboot,
            'execute' => $execute,
            'notify' => 0,
            'notes' => $notes,
            'created' => self::CREATED,
            'modified' => self::CREATED,
        ];
    }

    /**
     * Builds a hosts row.
     *
     * @param int $hostId Identifier.
     * @param string $name Regular expression matched against the workstation name.
     * @param int $mainProfileId Main profile.
     * @param int $position Order in hosts.xml.
     * @param string $notes Notes.
     * @param bool $enabled Whether the entry is enabled.
     * @return array<string, mixed>
     */
    private function host(
        int $hostId,
        string $name,
        int $mainProfileId,
        int $position,
        string $notes,
        bool $enabled = true,
    ): array {
        return [
            'id' => $hostId,
            'enabled' => $enabled ? 1 : 0,
            'name' => $name,
            'notes' => $notes,
            'created' => self::CREATED,
            'modified' => self::CREATED,
            'mainprofile_id' => $mainProfileId,
            'position' => $position,
        ];
    }
}
