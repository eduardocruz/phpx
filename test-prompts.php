<?php

require __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

// Test basic text input
info('Testing Laravel Prompts Installation');

$package = text(
    label: 'Enter a package name to test',
    placeholder: 'vendor/package',
    default: 'phpunit/phpunit'
);

// Test select menu
$version = select(
    label: 'Select a version constraint',
    options: [
        '^1.0' => 'Major version 1 (^1.0)',
        '^2.0' => 'Major version 2 (^2.0)',
        'dev-main' => 'Development version (dev-main)'
    ]
);

// Test confirmation
$confirm = confirm(
    label: 'Would you like to proceed with installation?',
    default: true
);

// Display results
info('Test Results:');
echo "Package: $package\n";
echo "Version: $version\n";
echo "Confirmed: " . ($confirm ? 'Yes' : 'No') . "\n";