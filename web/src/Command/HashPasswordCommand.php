<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Prints the Argon2id hash expected by WPKG_ADMIN_PASSWORD_HASH and WPKG_XML_PASSWORD_HASH.
 */
class HashPasswordCommand extends Command
{
    /**
     * Declares the command arguments.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->addArgument('password', ['help' => 'Password to hash.', 'required' => true]);
    }

    /**
     * Prints the hash.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $io->out(password_hash((string)$args->getArgument('password'), PASSWORD_ARGON2ID));

        return static::CODE_SUCCESS;
    }
}
