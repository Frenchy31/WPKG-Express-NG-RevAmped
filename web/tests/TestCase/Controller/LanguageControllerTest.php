<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use App\View\AppView;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\I18n\I18n;
use Cake\Routing\Router;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * English and French interface selector.
 */
class LanguageControllerTest extends AdminIntegrationTestCase
{
    private string $defaultLocale;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultLocale = (string)Configure::read('App.defaultLocale');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Configure::write('App.defaultLocale', $this->defaultLocale);
        I18n::setLocale($this->defaultLocale);
        parent::tearDown();
    }

    /**
     * A known value sets the cookie and returns to the page of origin.
     *
     * @return void
     */
    public function testSetFrenchStoresCookieAndRedirectsToReferer(): void
    {
        $this->configRequest(['headers' => ['Referer' => Router::fullBaseUrl() . '/packages']]);

        $this->post('/language', ['lang' => 'fr']);

        $this->assertRedirect('/packages');
        $this->assertCookie('fr', 'wpkg_lang');
        $cookie = $this->_response->getCookie('wpkg_lang');
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
    #[TestWith(['de'])]
    #[TestWith([''])]
    public function testUnknownValueSetsNoCookie(string $submitted): void
    {
        $this->post('/language', ['lang' => $submitted]);

        $this->assertRedirect('/');
        $this->assertNull($this->_response->getCookie('wpkg_lang'));
    }

    /**
     * An external Referer never leaves the application.
     *
     * @return void
     */
    public function testExternalRefererRedirectsToRoot(): void
    {
        $this->configRequest(['headers' => ['Referer' => 'https://evil.example/phish']]);

        $this->post('/language', ['lang' => 'fr']);

        $this->assertRedirect('/');
    }

    /**
     * Anonymous visitors can use the selector.
     *
     * @return void
     */
    public function testAnonymousVisitorCanSetLanguage(): void
    {
        $this->session(['Auth' => null]);

        $this->post('/language', ['lang' => 'fr']);

        $this->assertCookie('fr', 'wpkg_lang');
    }

    /**
     * Without cookie the interface stays English, whatever the browser asks for.
     *
     * @return void
     */
    public function testEnglishIsTheDefaultAndAcceptLanguageIsIgnored(): void
    {
        $this->configRequest(['headers' => ['Accept-Language' => 'fr-FR,fr;q=0.9']]);

        $this->get('/packages');

        $this->assertResponseContains('<html lang="en">');
        $this->assertResponseContains('Manage packages and associated information');
        $this->assertResponseRegExp('/<button[^>]*value="en"[^>]*aria-pressed="true"/');
        $this->assertResponseRegExp('/<button[^>]*value="fr"[^>]*aria-pressed="false"/');
    }

    /**
     * With the fr cookie the list, the selector and the footer are French.
     *
     * @return void
     */
    public function testFrenchCookieTranslatesPackageList(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/packages');

        $this->assertResponseOk();
        $this->assertResponseContains('<html lang="fr">');
        $this->assertResponseContains('Déconnexion');
        $this->assertResponseContains('Dernière modification');
        $this->assertResponseNotContains('Manage packages');
        $this->assertResponseContains('Langue' . "\u{a0}" . ':');
        $this->assertResponseRegExp('/<button[^>]*value="fr"[^>]*aria-pressed="true"/');
        $this->assertResponseRegExp('/<button[^>]*value="en"[^>]*aria-pressed="false"/');
    }

    /**
     * The login page honours the cookie of an anonymous visitor.
     *
     * @return void
     */
    public function testFrenchLoginPage(): void
    {
        $this->session(['Auth' => null]);
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/login');

        $this->assertResponseOk();
        $this->assertResponseContains('<html lang="fr">');
        $this->assertResponseContains('Mot de passe');
    }

    /**
     * Flash messages and error pages are French too.
     *
     * @return void
     */
    public function testFrenchFlashMessageAndErrorPage(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->post('/packages/delete/3');
        $this->assertFlashMessage('Package supprimé.');

        $this->get('/reports/INEXISTANT');
        $this->assertResponseCode(404);
        $this->assertResponseContains('Aucun rapport trouvé pour ce host.');
        $this->assertResponseContains('<html lang="fr">');
    }

    /**
     * Messages with arguments are translated as a whole.
     *
     * @return void
     */
    public function testFrenchConfirmationQuestion(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/packages');

        $this->assertResponseRegExp('/Supprimer le package «\x{a0}[^»]+\x{a0}»\x{a0}\?/u');
    }

    /**
     * APP_DEFAULT_LOCALE moves everyone who chose nothing to French; the cookie still wins.
     *
     * @return void
     */
    public function testServerDefaultLocale(): void
    {
        Configure::write('App.defaultLocale', 'fr_FR');

        $this->get('/packages');
        $this->assertResponseContains('<html lang="fr">');

        $this->cookie('wpkg_lang', 'en');
        $this->get('/packages');
        $this->assertResponseContains('<html lang="en">');
    }

    /**
     * Dates use 24 hours in French and 12 hours in English.
     *
     * @return void
     */
    public function testDateFormat(): void
    {
        $date = new DateTimeImmutable('2026-11-09 19:42:10');
        $view = new AppView(new ServerRequest(['url' => '/packages']));

        I18n::setLocale('en_US');
        $this->assertSame('2026-11-09 07:42:10 PM', $view->Wpkg->date($date));

        I18n::setLocale('fr_FR');
        $this->assertSame('2026-11-09 19:42:10', $view->Wpkg->date($date));
    }

    /**
     * Pluralised messages follow the French rule.
     *
     * @return void
     */
    public function testFrenchPlural(): void
    {
        $this->cookie('wpkg_lang', 'fr');

        $this->get('/search?q=a');

        $this->assertResponseRegExp('/résultats?<\/b> trouvés? pour la recherche/u');
    }
}
