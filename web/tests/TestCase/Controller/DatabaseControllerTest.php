<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\Support\AdminIntegrationTestCase;
use App\Test\Support\DatabaseSnapshotTrait;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\I18n;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Export, import and reset of the database from the Admin page.
 */
class DatabaseControllerTest extends AdminIntegrationTestCase
{
    use DatabaseSnapshotTrait;

    private const string NBSP = "\u{a0}";

    private const string TOO_LARGE = 'The file is larger than the upload limit of {limit}.';

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        I18n::setLocale((string)Configure::read('App.defaultLocale'));
        parent::tearDown();
    }

    /**
     * The three actions, which change or reveal the whole database.
     *
     * @return array<string, array{string}>
     */
    public static function actions(): array
    {
        return [
            'export' => ['/database/export'],
            'import' => ['/database/import'],
            'reset' => ['/database/reset'],
        ];
    }

    /**
     * The export is an attachment, dated, with the structure and the data.
     *
     * @return void
     */
    public function testExportDownloadsADatedSqlFile(): void
    {
        $this->post('/database/export');

        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Type', 'application/sql');
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="wpkg-express-ng-\d{8}-\d{6}\.sql"$/',
            $this->_response->getHeaderLine('Content-Disposition'),
        );
        $this->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame(
            (string)strlen((string)$this->_response->getBody()),
            $this->_response->getHeaderLine('Content-Length'),
        );
        $this->assertResponseContains("-- WPKG Express NG database export\n-- Format: 1\n-- Schema version: ");
        $this->assertResponseContains('CREATE TABLE `hosts` (');
        $this->assertResponseContains("INSERT INTO `hosts` (`id`,`enabled`,`name`");
        $this->assertResponseContains("'room1-pc.+'");
    }

    /**
     * The whole procedure of the Admin page: export, reset, import. The three feeds come back byte for byte, as
     * the workstations download them and in every format the feeds can take.
     *
     * @return void
     */
    public function testExportResetImportRestoresTheFeeds(): void
    {
        $snapshot = $this->snapshot();
        $feeds = $this->feedDocuments();
        $servedFeeds = $this->servedFeeds();
        $this->post('/database/export');
        $file = (string)$this->_response->getBody();

        $this->post('/database/reset');
        $this->assertSame(0, $this->rowCount());
        $this->assertNotSame($feeds, $this->feedDocuments());
        $this->assertNotSame($servedFeeds, $this->servedFeeds());

        $this->post('/database/import', ['backup' => $this->upload($file)]);

        $this->assertRedirect(['controller' => 'Admin', 'action' => 'index']);
        $this->assertFlashMessage(
            'The database was replaced with the content of the file: ' . $this->rowCount() . ' rows imported.',
        );
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame($feeds, $this->feedDocuments());
        $this->assertSame($servedFeeds, $this->servedFeeds());
    }

    /**
     * A reset empties the business tables and says so.
     *
     * @return void
     */
    public function testResetEmptiesTheDatabase(): void
    {
        $this->post('/database/reset');

        $this->assertRedirect(['controller' => 'Admin', 'action' => 'index']);
        $this->assertFlashMessage('The database was emptied.');
        $this->assertFlashElement('flash/success');
        $this->assertSame(0, $this->rowCount());
    }

    /**
     * An export is refused when it does not come from this application: the error is shown, the data is kept.
     *
     * @return void
     */
    public function testImportOfHandWrittenSqlShowsAnErrorAndKeepsTheData(): void
    {
        $snapshot = $this->snapshot();

        $this->post('/database/import', ['backup' => $this->upload("DROP TABLE hosts;\n")]);

        $this->assertRedirect(['controller' => 'Admin', 'action' => 'index']);
        $this->assertFlashMessage('This file is not an export of this application.');
        $this->assertFlashElement('flash/error');
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A truncated export is refused: the error is shown, the data is kept.
     *
     * @return void
     */
    public function testImportOfATruncatedExportShowsAnErrorAndKeepsTheData(): void
    {
        $snapshot = $this->snapshot();
        $this->post('/database/export');
        $file = (string)$this->_response->getBody();

        $this->post('/database/import', ['backup' => $this->upload(substr($file, 0, intdiv(strlen($file), 2)))]);

        $this->assertFlashElement('flash/error');
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * An export of another schema version is refused with a message naming both versions.
     *
     * @return void
     */
    public function testImportOfAnotherSchemaVersionShowsAnErrorAndKeepsTheData(): void
    {
        $snapshot = $this->snapshot();
        $this->post('/database/export');
        $file = preg_replace(
            '/^-- Schema version: \d+$/m',
            '-- Schema version: 20200101000000',
            (string)$this->_response->getBody(),
        );

        $this->post('/database/import', ['backup' => $this->upload((string)$file)]);

        $this->assertFlashElement('flash/error');
        $this->assertFlashMessage(
            'The file was exported from schema version 20200101000000, the database is at version '
            . $this->schemaVersion() . '. Import it into a database at the same version.',
        );
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Each way of sending no usable file gets its own message, and the data is kept.
     *
     * @return array<string, array{int, string}>
     */
    public static function uploadFailures(): array
    {
        return [
            'no file chosen' => [UPLOAD_ERR_NO_FILE, 'Select the file to import.'],
            'larger than upload_max_filesize' => [UPLOAD_ERR_INI_SIZE, self::TOO_LARGE],
            'larger than the form limit' => [UPLOAD_ERR_FORM_SIZE, self::TOO_LARGE],
            'partial upload' => [UPLOAD_ERR_PARTIAL, 'The file could not be uploaded.'],
            'no temporary directory' => [UPLOAD_ERR_NO_TMP_DIR, 'The file could not be uploaded.'],
        ];
    }

    /**
     * An upload that failed is reported to the administrator.
     *
     * @param int $error PHP upload error code.
     * @param string $message Expected message, {limit} standing for the upload limit.
     * @return void
     */
    #[DataProvider('uploadFailures')]
    public function testFailedUploadShowsAnErrorAndKeepsTheData(int $error, string $message): void
    {
        $snapshot = $this->snapshot();

        $this->post('/database/import', ['backup' => new UploadedFile('', 0, $error)]);

        $this->assertRedirect(['controller' => 'Admin', 'action' => 'index']);
        $this->assertFlashElement('flash/error');
        $this->assertFlashMessage(str_replace('{limit}', (string)ini_get('upload_max_filesize'), $message));
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A request without the file field is an error too, not a crash.
     *
     * @return void
     */
    public function testImportWithoutTheFileFieldShowsAnError(): void
    {
        $this->post('/database/import', ['other' => 'value']);

        $this->assertFlashMessage('Select the file to import.');
    }

    /**
     * Only POST reaches the actions: a link, an image or a prefetch cannot trigger them.
     *
     * @param string $url Action URL.
     * @return void
     */
    #[DataProvider('actions')]
    public function testActionsRefuseOtherMethods(string $url): void
    {
        $snapshot = $this->snapshot();

        $this->get($url);
        $this->assertResponseCode(405);
        $this->put($url);
        $this->assertResponseCode(405);
        $this->delete($url);
        $this->assertResponseCode(405);

        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * Visitors who are not logged in are sent to the login page, and nothing is exported, imported or emptied.
     *
     * @param string $url Action URL.
     * @return void
     */
    #[DataProvider('actions')]
    public function testActionsRequireTheLoggedInAdministrator(string $url): void
    {
        $snapshot = $this->snapshot();
        $this->session(['Auth' => null]);

        $this->post($url, ['backup' => $this->upload("-- not read\n")]);

        $this->assertRedirectContains('/login');
        $this->assertResponseNotContains('-- WPKG Express NG database export');
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * A request that does not carry the CSRF token of the session is rejected before the action runs.
     *
     * @param string $url Action URL.
     * @return void
     */
    #[DataProvider('actions')]
    public function testActionsRequireACsrfToken(string $url): void
    {
        $snapshot = $this->snapshot();
        $this->_csrfToken = false;

        $this->post($url, ['backup' => $this->upload("DROP TABLE hosts;\n")]);

        $this->assertResponseCode(403);
        $this->assertResponseNotContains('-- WPKG Express NG database export');
        $this->assertSame($snapshot, $this->snapshot());
    }

    /**
     * The Admin page offers the three actions as POST forms with a CSRF token, and the import and the reset warn
     * about the effect on the workstations.
     *
     * @return void
     */
    public function testAdminPageOffersTheActionsWithTheirWarning(): void
    {
        $this->get('/admin');

        $this->assertResponseOk();
        $this->assertResponseContains('<h2>Database</h2>');
        $page = (string)$this->_response->getBody();
        foreach (['export', 'import', 'reset'] as $action) {
            $form = [];
            $pattern = '#<form[^>]* method="post"[^>]* action="/database/' . $action . '".*?</form>#s';
            $this->assertSame(1, preg_match($pattern, $page, $form), $action);
            $this->assertStringContainsString('name="_csrfToken"', $form[0], $action);
        }
        $this->assertResponseContains('enctype="multipart/form-data"');
        $this->assertResponseContains('<input type="file" name="backup"');

        $warning = 'Packages missing from the new feeds will be uninstalled on the workstations at their next run.';
        $this->assertResponseContains('<div class="message warning">' . $warning . '</div>');
        foreach (['Replace the whole content of the database with this file', 'Empty the database'] as $question) {
            $this->assertMatchesRegularExpression(
                '/data-confirm-message="' . preg_quote($question, '/') . '\? ' . preg_quote($warning, '/') . '"/',
                $page,
            );
        }
    }

    /**
     * The section, its warning and the messages are translated, with the French punctuation rules.
     *
     * @return void
     */
    public function testFrenchInterface(): void
    {
        // The catalogue is cached for a year: one cached before these messages existed would hide them.
        Cache::clear('_cake_translations_');
        I18n::clear();
        $this->cookie('wpkg_lang', 'fr');
        $nbsp = self::NBSP;

        $this->get('/admin');
        $this->assertResponseContains('<h2>Base de données</h2>');
        $this->assertResponseContains(
            '<div class="message warning">Les packages absents des nouveaux flux seront désinstallés des postes '
            . 'à leur prochaine exécution.</div>',
        );
        $this->assertResponseContains(
            'data-confirm-message="Remplacer tout le contenu de la base par ce fichier' . $nbsp . '? Les packages',
        );
        $this->assertResponseContains('data-confirm-message="Vider la base' . $nbsp . '? Les packages');

        $file = "-- WPKG Express NG database export\n-- Format: 1\n-- Schema version: " . $this->schemaVersion()
            . "\nDROP TABLE hosts;\n";
        $this->post('/database/import', ['backup' => $this->upload($file)]);
        $this->assertFlashMessage("La ligne 4 ne fait pas partie d'un export de cette application.");

        $this->post('/database/reset');
        $this->assertFlashMessage('La base a été vidée.');
    }

    /**
     * Downloads hosts.xml, profiles.xml and packages.xml the way the workstations do.
     *
     * @return array<string, string> Body of each feed by file name.
     */
    private function servedFeeds(): array
    {
        $bodies = [];
        foreach (['hosts', 'profiles', 'packages'] as $feed) {
            $this->get('/' . $feed . '.xml');
            $this->assertResponseOk();
            $bodies[$feed . '.xml'] = (string)$this->_response->getBody();
        }

        return $bodies;
    }

    /**
     * Wraps a string as the file field of a form.
     *
     * @param string $content File content.
     * @return \Laminas\Diactoros\UploadedFile
     */
    private function upload(string $content): UploadedFile
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($content);
        $stream->rewind();

        return new UploadedFile($stream, strlen($content), UPLOAD_ERR_OK, 'backup.sql', 'application/sql');
    }

    /**
     * Returns the schema version of the test database.
     *
     * @return string
     */
    private function schemaVersion(): string
    {
        $migrations = glob(CONFIG . 'Migrations' . DS . '*.php') ?: [];

        return (string)max(array_map(fn(string $path): int => (int)basename($path), $migrations));
    }
}
