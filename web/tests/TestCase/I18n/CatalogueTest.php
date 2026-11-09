<?php

declare(strict_types=1);

namespace App\Test\TestCase\I18n;

use Cake\TestSuite\TestCase;

/**
 * Completeness of the French catalogue.
 */
class CatalogueTest extends TestCase
{
    /**
     * Reads a gettext file into a map from msgid to its list of translations.
     *
     * @param string $path File path.
     * @return array<string, array<int, string>>
     */
    private function readCatalogue(string $path): array
    {
        $entries = [];
        $currentId = null;
        $context = '';
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^msgctxt "(.*)"$/', $line, $matches)) {
                $context = $matches[1] . "\x04";
            } elseif (preg_match('/^msgid "(.*)"$/', $line, $matches)) {
                $currentId = $context . $matches[1];
                $context = '';
                $entries[$currentId] = [];
            } elseif ($currentId !== null && preg_match('/^msgstr(?:\[\d+\])? "(.*)"$/', $line, $matches)) {
                $entries[$currentId][] = $matches[1];
            }
        }
        unset($entries['']);

        return $entries;
    }

    /**
     * Every message of the template has a non-empty French translation.
     *
     * @return void
     */
    public function testEveryMsgidIsTranslatedInFrench(): void
    {
        $template = $this->readCatalogue(RESOURCES . 'locales' . DS . 'default.pot');
        $french = $this->readCatalogue(RESOURCES . 'locales' . DS . 'fr_FR' . DS . 'default.po');

        $this->assertNotEmpty($template);
        foreach ($template as $msgid => $unused) {
            $this->assertArrayHasKey($msgid, $french, 'Missing in fr_FR: ' . $msgid);
            $this->assertNotEmpty($french[$msgid], 'No translation: ' . $msgid);
            foreach ($french[$msgid] as $translation) {
                $this->assertNotSame('', $translation, 'Empty translation: ' . $msgid);
            }
        }
        $this->assertSame([], array_diff_key($french, $template), 'Obsolete messages in fr_FR');
    }

    /**
     * French punctuation takes a no-break space before : ; ? ! and inside guillemets.
     *
     * @return void
     */
    public function testFrenchTypography(): void
    {
        $french = $this->readCatalogue(RESOURCES . 'locales' . DS . 'fr_FR' . DS . 'default.po');

        foreach ($french as $msgid => $translations) {
            foreach ($translations as $translation) {
                $this->assertDoesNotMatchRegularExpression('/ [:;?!»]|« /u', $translation, $msgid);
            }
        }
    }
}
