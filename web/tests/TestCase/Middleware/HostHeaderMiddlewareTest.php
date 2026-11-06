<?php

declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Host validation in production mode: clients use names listed in hosts_path.
 */
class HostHeaderMiddlewareTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('debug', false);
        Configure::write('App.fullBaseUrl', 'https://wpkg.example.org');
        Configure::write('Wpkg.allowedHosts', 'wpkg, 10.0.0.5');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Configure::write('debug', true);
        parent::tearDown();
    }

    /**
     * Configured names are accepted.
     *
     * @param string $host Host header.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\TestWith(['wpkg.example.org'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['wpkg'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['10.0.0.5'])]
    public function testAcceptsConfiguredHosts(string $host): void
    {
        $this->configRequest(['headers' => ['Host' => $host], 'environment' => ['HTTP_HOST' => $host]]);

        $this->get('/login');

        $this->assertResponseOk();
    }

    /**
     * Anything else is refused.
     *
     * @return void
     */
    public function testRejectsOtherHosts(): void
    {
        $this->configRequest(['headers' => ['Host' => 'evil.example'], 'environment' => ['HTTP_HOST' => 'evil.example']]);

        $this->get('/login');

        $this->assertResponseCode(400);
    }
}
