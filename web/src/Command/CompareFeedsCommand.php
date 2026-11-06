<?php

declare(strict_types=1);

namespace App\Command;

use App\Feed\FeedComparator;
use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Compares the current feeds with feeds saved from the legacy application.
 *
 * A valid profile that lost its packages makes clients uninstall them, so this check
 * must pass on the migrated data before any client reads this application.
 */
class CompareFeedsCommand extends Command
{
    /**
     * Declares the command arguments.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->addArgument('directory', [
            'help' => 'Directory holding hosts.xml, profiles.xml and packages.xml saved from the legacy application.',
            'required' => true,
        ]);
    }

    /**
     * Compares the three feeds.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $directory = rtrim((string)$args->getArgument('directory'), '/');
        $comparator = new FeedComparator();
        $feeds = [
            'hosts' => new HostsFeed(true, false),
            'profiles' => new ProfilesFeed(true, false),
            'packages' => new PackagesFeed(true, false),
        ];

        $isIdentical = true;
        foreach ($feeds as $name => $feed) {
            $path = $directory . DS . $name . '.xml';
            if (!is_readable($path)) {
                $io->err(sprintf('%s: cannot read %s', $name, $path));
                $isIdentical = false;
                continue;
            }

            $legacyXml = (string)file_get_contents($path);
            $differences = $comparator->differences($legacyXml, $feed->document());
            if ($differences) {
                $isIdentical = false;
                $io->err(sprintf('%s: %d difference(s)', $name, count($differences)));
                foreach ($differences as $difference) {
                    $io->err('  ' . $difference);
                }
                continue;
            }
            $io->success(sprintf('%s: identical (%d elements)', $name, $comparator->elementCount($legacyXml)));
        }

        return $isIdentical ? static::CODE_SUCCESS : static::CODE_ERROR;
    }
}
