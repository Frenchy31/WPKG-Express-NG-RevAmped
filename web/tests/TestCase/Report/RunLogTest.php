<?php

declare(strict_types=1);

namespace App\Test\TestCase\Report;

use App\Report\ReportStatus;
use App\Report\RunLog;
use Cake\TestSuite\TestCase;

/**
 * Reading of the log files written by wpkg.js.
 */
class RunLogTest extends TestCase
{
    /**
     * Each line gives a time, a level and a message whose "|" and "\|" are decoded.
     *
     * @return void
     */
    public function testParsesEntries(): void
    {
        $log = RunLog::parse(
            "2026-11-08 03:00:05, ERROR   : Could not process (install) 7-Zip.|Failed checking after installation.\r\n"
            . "2026-11-08 03:00:06, WARNING : Command output: a \\| b\r\n"
            . "Restoring previous environment.\r\n",
        );

        $entries = $log->entries();
        $this->assertCount(3, $entries);
        $this->assertSame('2026-11-08 03:00:05', $entries[0]->time?->format('Y-m-d H:i:s'));
        $this->assertSame('ERROR', $entries[0]->level);
        $this->assertSame(
            "Could not process (install) 7-Zip.\nFailed checking after installation.",
            $entries[0]->message,
        );
        $this->assertSame('Command output: a | b', $entries[1]->message);
        $this->assertSame('level-warning', $entries[1]->cssClass());
        $this->assertNull($entries[2]->time);
        $this->assertNull($entries[2]->level);
        $this->assertSame('Restoring previous environment.', $entries[2]->message);
        $this->assertSame('level-raw', $entries[2]->cssClass());
    }

    /**
     * The start line gives the version and the time of the run, and the profiles line its profiles.
     *
     * @return void
     */
    public function testReadsRunFacts(): void
    {
        $log = RunLog::parse((string)file_get_contents(TESTS . 'Fixture' . DS . 'reports' . DS . 'ROOM1-PC01.log'));

        $this->assertSame('1.3.1', $log->wpkgVersion());
        $this->assertSame('2026-11-08 02:11:11', $log->startedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame(['base', 'room1'], $log->profiles());
        $this->assertSame(
            ['Processing (install) of 7-Zip successful.', 'Removal of Old Tool successful.'],
            $log->succeeded(),
        );
        $this->assertSame([], $log->errors());
        $this->assertSame(ReportStatus::Success, $log->status());
    }

    /**
     * An ERROR outweighs a WARNING, and a log without any level tells nothing.
     *
     * @return void
     */
    public function testStatus(): void
    {
        $error = "2026-11-08 03:00:05, ERROR   : failed\n2026-11-08 03:00:06, WARNING : odd\n";
        $warning = "2026-11-08 03:00:06, WARNING : odd\n2026-11-08 03:00:07, INFO    : done\n";

        $this->assertSame(ReportStatus::Failed, RunLog::parse($error)->status());
        $this->assertSame(['failed'], RunLog::parse($error)->errors());
        $this->assertSame(ReportStatus::Warning, RunLog::parse($warning)->status());
        $this->assertSame(['odd'], RunLog::parse($warning)->warnings());
        $this->assertSame(ReportStatus::Unknown, RunLog::parse("Log level is 255\n")->status());
        $this->assertSame(ReportStatus::Unknown, RunLog::parse('')->status());
    }

    /**
     * wpkg.js writes in the ANSI code page of the workstation.
     *
     * @return void
     */
    public function testConvertsWindows1252(): void
    {
        $log = RunLog::parse("2026-11-08 02:11:14, DEBUG   : Host operating system: windows 11 \xE9ducation\r\n");

        $this->assertSame('Host operating system: windows 11 éducation', $log->entries()[0]->message);
    }
}
