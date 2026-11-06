<?php

declare(strict_types=1);

namespace App\Test\TestCase\Feed;

use App\Feed\FeedComparator;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * Detection of feed differences, the safety net of a production cutover.
 */
class FeedComparatorTest extends TestCase
{
    /**
     * Differences clients cannot see are ignored.
     *
     * @return void
     */
    public function testIgnoresFormatting(): void
    {
        $legacy = "<?xml version=\"1.0\" ?>\n<packages>\n    <package id='a' name=\"A &amp; B\" />\n</packages>";
        $current = '<packages><package name="A &amp; B" id="a"></package></packages>';

        $this->assertSame([], (new FeedComparator())->differences($legacy, $current));
    }

    /**
     * Accepted differences: repaired escaping, restored counts, comments.
     *
     * @return void
     */
    public function testAcceptsKnownLegacyDefects(): void
    {
        $legacy = '<packages><!-- <package id="off" cmd="--x" /> --><install cmd="a & b" />'
            . '<check type="logical" condition="atleast" /></packages>';
        $current = '<packages><install cmd="a &amp; b"/><check type="logical" condition="atleast" value="2"/></packages>';

        $this->assertSame([], (new FeedComparator())->differences($legacy, $current));
    }

    /**
     * A lost link is what makes clients uninstall software.
     *
     * @return void
     */
    public function testReportsMissingElements(): void
    {
        $legacy = '<profiles><profile id="p"><package package-id="a"/><package package-id="b"/></profile></profiles>';
        $current = '<profiles><profile id="p"><package package-id="a"/></profile></profiles>';

        $this->assertSame(['- package package-id="b"'], (new FeedComparator())->differences($legacy, $current));
    }

    /**
     * A revision change would upgrade every client.
     *
     * @return void
     */
    public function testReportsChangedAttributes(): void
    {
        $differences = (new FeedComparator())->differences(
            '<packages><package id="a" revision="1"/></packages>',
            '<packages><package id="a" revision="2"/></packages>',
        );

        $this->assertSame(['- package id="a" revision="1"', '+ package id="a" revision="2"'], array_map(trim(...), $differences));
    }

    /**
     * Host order decides which entry matches first.
     *
     * @return void
     */
    public function testReportsOrderChanges(): void
    {
        $differences = (new FeedComparator())->differences(
            '<wpkg><host name="a"/><host name="b"/></wpkg>',
            '<wpkg><host name="b"/><host name="a"/></wpkg>',
        );

        $this->assertSame(['Same elements, in a different order.'], $differences);
    }

    /**
     * Unreadable documents are an error, not an empty difference.
     *
     * @return void
     */
    public function testRejectsMalformedDocument(): void
    {
        $this->expectException(RuntimeException::class);

        (new FeedComparator())->differences('<wpkg/>', '<wpkg>');
    }
}
