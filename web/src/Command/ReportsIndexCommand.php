<?php

declare(strict_types=1);

namespace App\Command;

use App\Report\ReportDirectory;
use App\Report\ReportIndexer;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * Reads the new and changed report files of the share into the host_reports table.
 *
 * The Reports pages read that table, never the share. Run it in a loop: see the reports-indexer
 * service of services/wpkg/compose.yaml.
 */
class ReportsIndexCommand extends Command
{
    /**
     * Name of the MySQL lock that keeps two passes apart, whatever container they run in.
     */
    private const string LOCK_NAME = 'wpkg_reports_index';

    /**
     * Names the command.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'reports index';
    }

    /**
     * Describes the command.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(
            'Indexes the report files of WPKG_REPORTS_PATH into the host_reports table.',
        );
    }

    /**
     * Runs one pass.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $directory = ReportDirectory::fromConfiguration();
        if (!$directory->isConfigured()) {
            $io->out('WPKG_REPORTS_PATH is not set: nothing to index.');

            return static::CODE_SUCCESS;
        }

        $connection = ConnectionManager::get('default');
        $isLocked = (int)$connection->execute('SELECT GET_LOCK(?, 0)', [self::LOCK_NAME])->fetchColumn(0) === 1;
        if (!$isLocked) {
            $io->out('Another pass is running: nothing done.');

            return static::CODE_SUCCESS;
        }

        try {
            $state = (new ReportIndexer($directory))->run();
        } finally {
            $connection->execute('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        }

        if ($state->error !== null) {
            $io->err($state->error);
        }
        $io->out(sprintf(
            'Files seen: %d, files read: %d, hosts removed: %d, duration: %.1f s.',
            $state->filesSeen,
            $state->filesRead,
            $state->hostsRemoved,
            $state->durationMs / 1000,
        ));

        return $state->error === null ? static::CODE_SUCCESS : static::CODE_ERROR;
    }
}
