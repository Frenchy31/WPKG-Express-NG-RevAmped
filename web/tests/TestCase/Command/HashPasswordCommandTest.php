<?php

declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Password hash generation.
 */
class HashPasswordCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * The printed hash verifies the password.
     *
     * @return void
     */
    public function testPrintsVerifiableHash(): void
    {
        $this->exec('hash_password s3cret-pass');

        $this->assertExitSuccess();
        $hash = trim(implode('', $this->_out->messages()));
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(password_verify('s3cret-pass', $hash));
    }
}
