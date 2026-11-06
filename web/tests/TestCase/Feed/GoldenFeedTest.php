<?php

declare(strict_types=1);

namespace App\Test\TestCase\Feed;

use App\Feed\FeedComparator;
use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use App\Feed\XmlFeed;
use App\Test\Support\ReferenceDataTrait;
use Cake\TestSuite\TestCase;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Compares the feeds with the output captured from the frozen PHP 5.6 application.
 *
 * A difference here can make clients uninstall or upgrade software. `FeedComparator` holds the
 * exhaustive list of accepted differences.
 */
class GoldenFeedTest extends TestCase
{
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
     * Lists every captured document.
     *
     * @return array<string, array{string, string, bool, bool}>
     */
    public static function documents(): array
    {
        $documents = [];
        foreach (['default' => [true, false], 'noformat' => [false, false], 'exportdisabled' => [true, true]] as $scenario => $options) {
            foreach (['hosts', 'profiles', 'packages'] as $feedName) {
                $documents["$scenario/$feedName"] = [$scenario, $feedName, $options[0], $options[1]];
            }
        }

        return $documents;
    }

    /**
     * The generated document matches the legacy document, comments aside.
     *
     * @param string $scenario Capture scenario.
     * @param string $feedName Feed name.
     * @param bool $format Indent the output.
     * @param bool $exportDisabled Emit disabled records as comments.
     * @return void
     */
    #[DataProvider('documents')]
    public function testDocumentMatchesLegacyOutput(string $scenario, string $feedName, bool $format, bool $exportDisabled): void
    {
        $actual = $this->feed($feedName, $format, $exportDisabled)->document();

        $this->assertSame([], (new FeedComparator())->differences($this->golden("$scenario/$feedName.xml"), $actual));
    }

    /**
     * Single-record documents match the legacy output.
     *
     * @return void
     */
    public function testSingleRecordsMatchLegacyOutput(): void
    {
        $comparator = new FeedComparator();
        $this->assertSame([], $comparator->differences(
            $this->golden('default/host-view-1.xml'),
            (string)$this->feed('hosts', true, false)->record(1),
        ));
        $this->assertSame([], $comparator->differences(
            $this->golden('default/profile-view-1.xml'),
            (string)$this->feed('profiles', true, false)->record(1),
        ));
        $this->assertSame([], $comparator->differences(
            $this->golden('default/package-view-2.xml'),
            (string)$this->feed('packages', true, false)->record(2),
        ));
    }

    /**
     * A disabled record is not exposed. The legacy application answered with an empty document.
     *
     * @return void
     */
    public function testDisabledRecordIsNotExposed(): void
    {
        $this->assertNull($this->feed('hosts', true, false)->record(3));
        $this->assertNull($this->feed('packages', true, false)->record(7));
    }

    /**
     * Commands with shell metacharacters survive a parse, which is what clients do.
     *
     * @return void
     */
    public function testCommandsAreEscaped(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($this->feed('packages', true, false)->document()));

        $commands = [];
        foreach ($document->getElementsByTagName('install') as $install) {
            $commands[] = $install->getAttribute('cmd');
        }

        $this->assertContains('cmd /c echo a & echo b', $commands);
        $this->assertContains('cmd /c "echo a & echo b"', $commands);
        $this->assertContains('cmd /c type foo.txt > bar.txt', $commands);
        $this->assertContains('cmd /c "echo it\'s done"', $commands);
        $this->assertContains('"%TEMP%\7zip\7zsetup.exe" /S', $commands);
    }

    /**
     * Disabled records appear as comments without breaking the document.
     *
     * @return void
     */
    public function testDisabledRecordCommentIsWellFormed(): void
    {
        $xml = $this->feed('packages', true, true)->document();

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        $this->assertStringContainsString('id="disabled-dash"', $xml);
        $this->assertStringContainsString('setup.exe - -silent - -no-restart', $xml);
    }

    /**
     * Quantified checks carry the value wpkg.js requires.
     *
     * @return void
     */
    public function testLogicalAtLeastKeepsItsValue(): void
    {
        $xml = $this->feed('packages', true, false)->document();

        $this->assertStringContainsString('<check type="logical" condition="atleast" value="2">', $xml);
        $this->assertStringContainsString('<check type="logical" condition="atmost" value="1"/>', $xml);
    }

    /**
     * Generated documents satisfy the schemas shipped with wpkg.js, once the root is namespaced.
     *
     * @param string $scenario Capture scenario.
     * @param string $feedName Feed name.
     * @param bool $format Indent the output.
     * @param bool $exportDisabled Emit disabled records as comments.
     * @return void
     */
    #[DataProvider('documents')]
    public function testDocumentSatisfiesClientSchema(string $scenario, string $feedName, bool $format, bool $exportDisabled): void
    {
        $xml = $this->feed($feedName, $format, $exportDisabled)->document();
        $root = $feedName === 'hosts' ? 'wpkg' : $feedName;
        $namespaced = (string)preg_replace(
            ['/<' . $root . '>/', '/<\/' . $root . '>/'],
            ['<ns:' . $root . ' xmlns:ns="http://www.wpkg.org/' . $feedName . '">', '</ns:' . $root . '>'],
            $xml,
        );

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($namespaced));

        libxml_use_internal_errors(true);
        $valid = $document->schemaValidate(RESOURCES . 'xsd' . DS . $feedName . '.xsd');
        $errors = array_map(fn($error): string => trim($error->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->assertTrue($valid, implode("\n", $errors));
    }

    /**
     * Reads a captured document.
     *
     * @param string $path Path relative to the golden directory.
     * @return string
     */
    private function golden(string $path): string
    {
        return (string)file_get_contents(TESTS . 'Fixture' . DS . 'reference' . DS . 'golden' . DS . $path);
    }

    /**
     * Builds a feed.
     *
     * @param string $feedName Feed name.
     * @param bool $format Indent the output.
     * @param bool $exportDisabled Emit disabled records as comments.
     * @return \App\Feed\XmlFeed
     */
    private function feed(string $feedName, bool $format, bool $exportDisabled): XmlFeed
    {
        return match ($feedName) {
            'hosts' => new HostsFeed($format, $exportDisabled),
            'profiles' => new ProfilesFeed($format, $exportDisabled),
            'packages' => new PackagesFeed($format, $exportDisabled),
        };
    }
}
