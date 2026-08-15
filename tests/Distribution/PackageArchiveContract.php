<?php

declare(strict_types=1);

namespace Tests\Distribution;

final class PackageArchiveContract
{
    /** @var list<string> */
    public const REQUIRED_FILES = [
        'CHANGELOG.md',
        'LICENSE',
        'README.md',
        'composer.json',
        'config/modules.php',
        'src/Module.php',
        'stubs/module.stub',
    ];

    /** @var list<string> */
    public const EXCLUDED_TREES = [
        '.github/',
        'benchmarks/',
        'tests/',
        'workbench/',
    ];

    /** @var list<string> */
    public const EXCLUDED_FILES = [
        '.git',
        '.gitattributes',
        '.gitignore',
        'phpstan.neon.dist',
        'phpunit.xml.dist',
        'testbench.yaml',
    ];

    private function __construct()
    {
    }
}
