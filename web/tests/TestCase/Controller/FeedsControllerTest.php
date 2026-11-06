<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\ReferenceDataTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * HTTP contract of the feeds read by wpkg.js.
 */
class FeedsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use ReferenceDataTrait;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadReferenceData();
        Configure::write('Wpkg.xml.protect', false);
        Configure::write('Wpkg.xml.user', 'wpkg');
        Configure::write('Wpkg.xml.passwordHash', password_hash('feed-secret', PASSWORD_ARGON2ID));
    }

    /**
     * Clients read the three feeds anonymously.
     *
     * @return void
     */
    public function testFeedsAreAnonymousXml(): void
    {
        foreach (['/hosts.xml' => '<wpkg>', '/profiles.xml' => '<profiles>', '/packages.xml' => '<packages>'] as $url => $root) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertHeaderContains('Content-Type', 'text/xml');
            $this->assertResponseContains($root);
        }
    }

    /**
     * Single-record URLs keep working, and hide disabled records.
     *
     * @return void
     */
    public function testRecordUrls(): void
    {
        $this->get('/hosts/view/1.xml');
        $this->assertResponseOk();
        $this->assertResponseContains('<host name="room1-pc.+" profile-id="base">');

        $this->get('/hosts/view/3.xml');
        $this->assertResponseCode(404);

        $this->get('/packages/view/2.xml');
        $this->assertResponseOk();
        $this->get('/profiles/view/1.xml');
        $this->assertResponseOk();
    }

    /**
     * The protection the legacy application never enforced now applies.
     *
     * @return void
     */
    public function testProtectedFeedRequiresCredentials(): void
    {
        Configure::write('Wpkg.xml.protect', true);

        $this->get('/hosts.xml');
        $this->assertResponseCode(401);
        $this->assertHeaderContains('WWW-Authenticate', 'Basic realm="wpkgExpress XML Export"');

        $this->configRequest(['environment' => ['PHP_AUTH_USER' => 'wpkg', 'PHP_AUTH_PW' => 'wrong']]);
        $this->get('/hosts.xml');
        $this->assertResponseCode(401);

        $this->_request = [];
        $this->configRequest(['environment' => ['PHP_AUTH_USER' => 'wpkg', 'PHP_AUTH_PW' => 'feed-secret']]);
        $this->get('/hosts.xml');
        $this->assertResponseOk();
    }
}
