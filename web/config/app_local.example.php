<?php
declare(strict_types=1);

use function Cake\Core\env;

/*
 * Environment configuration. Every deployment-specific value comes from environment variables,
 * so this file carries no secret and the image needs no web installer.
 */
return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],

    'Datasources' => [
        'default' => [
            'url' => env('DATABASE_URL'),
            // package_checks.condition is a MySQL reserved word.
            'quoteIdentifiers' => true,
        ],
        'test' => [
            'url' => env('DATABASE_TEST_URL'),
            'quoteIdentifiers' => true,
        ],
    ],
];
