<?php

declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Test\Support\ReferenceDataTrait;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Cutover check.
 */
class CompareFeedsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use ReferenceDataTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
    }

    /**
     * Intact data passes.
     *
     * @return void
     */
    public function testPassesOnIntactData(): void
    {
        $this->exec('compare_feeds ' . $this->goldenDirectory());

        $this->assertExitSuccess();
        $this->assertOutputContains('packages: identical');
    }

    /**
     * Lost package links, the dangerous case, fail the check and name the package.
     *
     * @return void
     */
    public function testFailsWhenPackageLinksAreLost(): void
    {
        ConnectionManager::get('test')->execute('DELETE FROM packages_profiles WHERE package_id = 1');

        $this->exec('compare_feeds ' . $this->goldenDirectory());

        $this->assertExitError();
        $this->assertErrorContains('package package-id="7zip"');
    }

    /**
     * A missing capture fails instead of passing silently.
     *
     * @return void
     */
    public function testFailsOnMissingDirectory(): void
    {
        $this->exec('compare_feeds /does/not/exist');

        $this->assertExitError();
        $this->assertErrorContains('cannot read');
    }

    /**
     * Returns the directory of the reference capture.
     *
     * @return string
     */
    private function goldenDirectory(): string
    {
        return TESTS . 'Fixture' . DS . 'reference' . DS . 'golden' . DS . 'default';
    }
}
