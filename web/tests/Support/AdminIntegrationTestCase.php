<?php

declare(strict_types=1);

namespace App\Test\Support;

use ArrayObject;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Base class of HTTP tests: reference data, logged-in administrator, valid CSRF tokens.
 */
abstract class AdminIntegrationTestCase extends TestCase
{
    use IntegrationTestTrait;
    use LocatorAwareTrait;
    use ReferenceDataTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
        $this->session(['Auth' => new ArrayObject(['id' => 1, 'username' => 'admin'])]);
        $this->enableCsrfToken();
        $this->enableRetainFlashMessages();
        Configure::write('Wpkg.xml.protect', false);
        Configure::write('Wpkg.xml.exportDisabled', false);
        Configure::write('Wpkg.xml.format', true);
    }
}
