<?php

declare(strict_types=1);

namespace App\Identifier;

use ArrayAccess;
use ArrayObject;
use Authentication\Identifier\AbstractIdentifier;
use Cake\Core\Configure;

/**
 * Authenticates the single administrator account defined by the environment.
 */
class AdminIdentifier extends AbstractIdentifier
{
    /**
     * Checks a username and password against the configured account.
     *
     * @param array<string, mixed> $credentials Submitted credentials.
     * @return \ArrayAccess<string, mixed>|array<string, mixed>|null
     */
    public function identify(array $credentials): ArrayAccess|array|null
    {
        $username = (string)($credentials['username'] ?? '');
        $password = (string)($credentials['password'] ?? '');
        $expectedHash = (string)Configure::read('Wpkg.adminPasswordHash');

        $isExpectedUser = hash_equals((string)Configure::read('Wpkg.adminUser'), $username);
        // password_verify runs even for an unknown user so timing does not reveal the account name.
        $isExpectedPassword = password_verify($password, $expectedHash);

        return $isExpectedUser && $isExpectedPassword ? new ArrayObject(['id' => 1, 'username' => $username]) : null;
    }
}
