<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Light and dark theme selector.
 */
class ThemeControllerTest extends AdminIntegrationTestCase
{
    /**
     * A known value sets the cookie and returns to the page of origin.
     *
     * @return void
     */
    public function testSetDarkStoresCookieAndRedirectsToReferer(): void
    {
        $this->configRequest(['headers' => ['Referer' => 'http://wpkg.localhost/packages']]);

        $this->post('/theme', ['theme' => 'dark']);

        $this->assertRedirect('/packages');
        $this->assertCookie('dark', 'wpkg_theme');
        $cookie = $this->_response->getCookie('wpkg_theme');
        $this->assertSame('/', $cookie['path']);
        $this->assertTrue($cookie['httponly']);
        $this->assertSame('Lax', $cookie['samesite']);
        $this->assertEqualsWithDelta(time() + 365 * 86400, $cookie['expires'], 60);
    }

    /**
     * An unknown value is ignored.
     *
     * @param string $submitted Submitted value.
     * @return void
     */
    #[TestWith(['sepia'])]
    #[TestWith([''])]
    public function testUnknownValueSetsNoCookie(string $submitted): void
    {
        $this->post('/theme', ['theme' => $submitted]);

        $this->assertRedirect('/');
        $this->assertNull($this->_response->getCookie('wpkg_theme'));
    }

    /**
     * An external Referer never leaves the application.
     *
     * @return void
     */
    public function testExternalRefererRedirectsToRoot(): void
    {
        $this->configRequest(['headers' => ['Referer' => 'https://evil.example/phish']]);

        $this->post('/theme', ['theme' => 'light']);

        $this->assertRedirect('/');
    }

    /**
     * The layout writes data-theme from the cookie and nothing in auto mode.
     *
     * @return void
     */
    public function testLayoutWritesThemeAttribute(): void
    {
        $this->cookie('wpkg_theme', 'dark');
        $this->get('/packages');
        $this->assertResponseContains('<html lang="en" data-theme="dark">');
        $this->assertResponseContains('<meta name="color-scheme" content="light dark">');
        $this->assertResponseRegExp('/<button[^>]*value="dark"[^>]*aria-pressed="true"/');
        $this->assertResponseRegExp('/<button[^>]*value="auto"[^>]*aria-pressed="false"/');

        $this->cookie('wpkg_theme', 'auto');
        $this->get('/packages');
        $this->assertResponseContains('<html lang="en">');
        $this->assertResponseNotContains('data-theme');

        $this->cookie('wpkg_theme', 'bogus');
        $this->get('/packages');
        $this->assertResponseNotContains('data-theme');
    }

    /**
     * The login page honours the cookie of an anonymous visitor.
     *
     * @return void
     */
    public function testLoginLayoutWritesThemeAttribute(): void
    {
        $this->session(['Auth' => null]);
        $this->cookie('wpkg_theme', 'light');

        $this->get('/login');

        $this->assertResponseOk();
        $this->assertResponseContains('<html lang="en" data-theme="light">');
    }

    /**
     * Anonymous visitors can use the selector.
     *
     * @return void
     */
    public function testAnonymousVisitorCanSetTheme(): void
    {
        $this->session(['Auth' => null]);

        $this->post('/theme', ['theme' => 'dark']);

        $this->assertCookie('dark', 'wpkg_theme');
    }
}
