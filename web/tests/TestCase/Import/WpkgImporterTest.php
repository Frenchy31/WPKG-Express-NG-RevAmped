<?php

declare(strict_types=1);

namespace App\Test\TestCase\Import;

use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use App\Import\WpkgImporter;
use App\Test\Support\ReferenceDataTrait;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;
use DOMDocument;

/**
 * Import of WPKG XML documents.
 */
class WpkgImporterTest extends TestCase
{
    use LocatorAwareTrait;
    use ReferenceDataTrait;

    private WpkgImporter $importer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
        $this->importer = new WpkgImporter();
    }

    /**
     * Exporting, importing into an empty database and exporting again changes nothing.
     *
     * @return void
     */
    public function testRoundTripPreservesFeeds(): void
    {
        $packages = (new PackagesFeed(true, false))->document();
        $profiles = (new ProfilesFeed(true, false))->document();
        $hosts = (new HostsFeed(true, false))->document();

        $this->emptyDatabase();

        foreach (['packages' => $packages, 'profiles' => $profiles, 'hosts' => $hosts] as $kind => $xml) {
            $report = $this->importer->import($kind, $xml);
            $this->assertSame([], $report->errors, $kind);
            $this->assertSame($report->total, $report->saved, $kind);
        }

        $this->assertSame($this->canonical($packages), $this->canonical((new PackagesFeed(true, false))->document()));
        $this->assertSame($this->canonical($profiles), $this->canonical((new ProfilesFeed(true, false))->document()));
        $this->assertSame($this->canonical($hosts), $this->canonical((new HostsFeed(true, false))->document()));
    }

    /**
     * Namespaced files, the usual format on a WPKG share, are accepted.
     *
     * @return void
     */
    public function testImportsNamespacedDocument(): void
    {
        $report = $this->importer->import('packages', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <packages:packages xmlns:packages="http://www.wpkg.org/packages" xmlns:wpkg="http://www.wpkg.org/wpkg">
                <package id="firefox" name="Firefox" revision="120" reboot="false" priority="10">
                    <check type="uninstall" condition="exists" path="Mozilla Firefox.*"/>
                    <install cmd="setup.exe /S"><exit code="0"/></install>
                </package>
            </packages:packages>
            XML);

        $this->assertSame([], $report->errors);
        $this->assertSame(1, $report->saved);
        $this->assertTrue($this->fetchTable('Packages')->exists(['id_text' => 'firefox']));
    }

    /**
     * An unparsable or invalid document changes nothing.
     *
     * @return void
     */
    public function testRejectsInvalidDocuments(): void
    {
        $before = $this->fetchTable('Packages')->find()->count();

        $malformed = $this->importer->import('packages', '<packages><package id="a" & /></packages>');
        $this->assertTrue($malformed->rejected);
        $this->assertNotEmpty($malformed->errors);

        $invalid = $this->importer->import('packages', '<packages><nonsense/></packages>');
        $this->assertTrue($invalid->rejected);

        $this->assertSame($before, $this->fetchTable('Packages')->find()->count());
    }

    /**
     * A higher revision replaces a package and keeps its links; children are rebuilt.
     *
     * @return void
     */
    public function testHigherRevisionReplacesPackage(): void
    {
        $report = $this->importer->import('packages', $this->singlePackage('7zip', '24.0', 'new.exe /S'));

        $this->assertSame(1, $report->saved);
        $this->assertStringContainsString('upgraded from revision 23.01 to 24.0', $report->notices[0]);

        $package = $this->fetchTable('Packages')->find()
            ->where(['id_text' => '7zip'])
            ->contain(['PackageActions', 'Profiles', 'DependedOnBy'])
            ->firstOrFail();
        $this->assertSame('24.0', $package->revision);
        $this->assertSame(['new.exe /S'], array_map(fn($action) => $action->command, $package->package_actions));
        $this->assertSame(['base'], array_map(fn($profile) => $profile->id_text, $package->profiles));
        $this->assertSame(['dep-child'], array_map(fn($dependent) => $dependent->id_text, $package->depended_on_by));
    }

    /**
     * An equal or lower revision is skipped.
     *
     * @return void
     */
    public function testLowerRevisionIsSkipped(): void
    {
        $report = $this->importer->import('packages', $this->singlePackage('7zip', '23.01', 'old.exe'));

        $this->assertSame(0, $report->saved);
        $this->assertNotEmpty($report->warnings);
        $this->assertSame('23.01', $this->fetchTable('Packages')->find()->where(['id_text' => '7zip'])->firstOrFail()->revision);
    }

    /**
     * An invalid replacement leaves the stored package untouched.
     *
     * @return void
     */
    public function testInvalidReplacementRollsBack(): void
    {
        $report = $this->importer->import('packages', <<<'XML'
            <packages>
                <package id="7zip" name="7-Zip" revision="99" priority="1">
                    <install cmd="ok.exe"><exit code="not-a-number"/></install>
                </package>
            </packages>
            XML);

        $this->assertSame(0, $report->saved);
        $this->assertNotEmpty($report->errors);

        $package = $this->fetchTable('Packages')->find()
            ->where(['id_text' => '7zip'])->contain(['PackageActions', 'Profiles'])->firstOrFail();
        $this->assertSame('23.01', $package->revision);
        $this->assertCount(3, $package->package_actions);
        $this->assertCount(1, $package->profiles);
    }

    /**
     * Unknown dependencies block the package; dependencies inside the file are resolved.
     *
     * @return void
     */
    public function testPackageDependencies(): void
    {
        $report = $this->importer->import('packages', <<<'XML'
            <packages>
                <package id="child" name="Child" revision="1" priority="1"><depends package-id="parent"/></package>
                <package id="parent" name="Parent" revision="1" priority="1"/>
                <package id="orphan" name="Orphan" revision="1" priority="1"><depends package-id="ghost"/></package>
            </packages>
            XML);

        $this->assertSame(2, $report->saved);
        $this->assertStringContainsString('ghost', $report->errors[0]);
        $this->assertFalse($this->fetchTable('Packages')->exists(['id_text' => 'orphan']));

        $child = $this->fetchTable('Packages')->find()->where(['id_text' => 'child'])->contain(['PackageDependencies'])->firstOrFail();
        $this->assertSame(['parent'], array_map(fn($package) => $package->id_text, $child->package_dependencies));
    }

    /**
     * Elements the database cannot store are reported instead of silently lost.
     *
     * @return void
     */
    public function testWarnsAboutUnsupportedElements(): void
    {
        $report = $this->importer->import('packages', <<<'XML'
            <packages>
                <package id="dl" name="Download" revision="1" priority="1">
                    <install cmd="%TEMP%\setup.exe"><download url="https://example.org/setup.exe" target="setup.exe"/></install>
                </package>
            </packages>
            XML);

        $this->assertSame(1, $report->saved);
        $this->assertStringContainsString('<download>', implode(' ', $report->warnings));
    }

    /**
     * Profiles need existing packages, hosts need existing profiles.
     *
     * @return void
     */
    public function testProfilesAndHostsNeedTheirReferences(): void
    {
        $profiles = $this->importer->import('profiles', '<profiles><profile id="x"><package package-id="ghost"/></profile></profiles>');
        $this->assertSame(0, $profiles->saved);
        $this->assertStringContainsString('ghost', $profiles->errors[0]);

        $hosts = $this->importer->import('hosts', '<wpkg><host name="pc-.+" profile-id="ghost"/></wpkg>');
        $this->assertSame(0, $hosts->saved);
        $this->assertStringContainsString('ghost', $hosts->errors[0]);

        $existing = $this->importer->import('hosts', '<wpkg><host name="room1-pc.+" profile-id="base"/></wpkg>');
        $this->assertSame(0, $existing->saved);
        $this->assertStringContainsString('already exists', $existing->errors[0]);
    }

    /**
     * Builds a document with one package.
     *
     * @param string $identifier Package identifier.
     * @param string $revision Revision.
     * @param string $command Install command.
     * @return string
     */
    private function singlePackage(string $identifier, string $revision, string $command): string
    {
        return sprintf(
            '<packages><package id="%s" name="%s" revision="%s" priority="1"><install cmd="%s"/></package></packages>',
            $identifier,
            $identifier,
            $revision,
            $command,
        );
    }

    /**
     * Empties the application tables.
     *
     * @return void
     */
    private function emptyDatabase(): void
    {
        $connection = ConnectionManager::get('test');
        foreach (
            [
            'exit_codes', 'hosts', 'hosts_profiles', 'package_actions', 'package_checks', 'packages',
            'packages_packages', 'packages_profiles', 'profiles', 'profiles_profiles', 'variables',
            ] as $table
        ) {
            $connection->execute('TRUNCATE TABLE `' . $table . '`');
        }
    }

    /**
     * Normalizes a document.
     *
     * @param string $xml Document.
     * @return string
     */
    private function canonical(string $xml): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($xml);

        return (string)$document->C14N(false, false);
    }
}
