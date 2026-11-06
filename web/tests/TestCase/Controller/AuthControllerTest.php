<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\ReferenceDataTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Access control of the web interface.
 */
class AuthControllerTest extends TestCase
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
        Configure::write('Wpkg.adminUser', 'admin');
        Configure::write('Wpkg.adminPasswordHash', password_hash('s3cret-pass', PASSWORD_ARGON2ID));
        $this->enableCsrfToken();
        $this->enableRetainFlashMessages();
    }

    /**
     * Every page requires a login.
     *
     * @return void
     */
    public function testPagesRedirectAnonymousVisitorsToLogin(): void
    {
        foreach (['/', '/packages', '/profiles', '/hosts', '/admin', '/search?q=a', '/variables/view/host/1'] as $url) {
            $this->get($url);
            $this->assertRedirectContains('/login');
        }
    }

    /**
     * Correct credentials open a session.
     *
     * @return void
     */
    public function testLoginWithValidCredentials(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 's3cret-pass']);

        $this->assertRedirect('/');
        $this->assertSessionHasKey('Auth');
    }

    /**
     * Wrong credentials are refused.
     *
     * @param array<string, string> $credentials Submitted fields.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\TestWith([['username' => 'admin', 'password' => 'nope']])]
    #[\PHPUnit\Framework\Attributes\TestWith([['username' => 'root', 'password' => 's3cret-pass']])]
    public function testLoginWithInvalidCredentials(array $credentials): void
    {
        $this->post('/login', $credentials);

        $this->assertResponseOk();
        $this->assertFlashMessage('Incorrect username or password');
        $this->assertSession(null, 'Auth');
    }

    /**
     * No configured hash means nobody can log in.
     *
     * @return void
     */
    public function testLoginFailsWithoutConfiguredHash(): void
    {
        Configure::write('Wpkg.adminPasswordHash', '');

        $this->post('/login', ['username' => 'admin', 'password' => '']);

        $this->assertSession(null, 'Auth');
    }

    /**
     * Requests changing data need a CSRF token.
     *
     * @return void
     */
    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->expectException(\Cake\Http\Exception\InvalidCsrfTokenException::class);

        $this->_csrfToken = false;
        $this->post('/login', ['username' => 'admin', 'password' => 's3cret-pass']);
    }
}
