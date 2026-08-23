<?php

declare(strict_types=1);

namespace Tests\Distribution;

final class PackageArchiveContract
{
    /** @var list<string> */
    public const REQUIRED_FILES = [
        'CHANGELOG.md',
        'CONTRIBUTING.md',
        'LICENSE',
        'README.md',
        'SECURITY.md',
        'UPGRADING.md',
        'composer.json',
        'config/moduark.php',
        'docs/releases.md',
        'docs/stability.md',
        'resources/boost/skills/moduark-development/SKILL.md',
        'resources/boost/skills/moduark-development/references/adoption-and-levels.md',
        'resources/boost/skills/moduark-development/references/diagnostics-and-debt.md',
        'resources/boost/skills/moduark-development/references/inspection-and-upgrades.md',
        'src/Analysis/Suppression/SuppressionArchitectureCheck.php',
        'src/Module.php',
        'stubs/module.stub',
        'stubs/module-component.inline.stub',
        'stubs/module-component.stub',
        'stubs/module-component-view.stub',
        'stubs/module-factory.stub',
        'stubs/module-migration.create.stub',
        'stubs/module-migration.stub',
        'stubs/module-migration.update.stub',
        'stubs/module-model.factory.stub',
        'stubs/module-preset-api-controller.stub',
        'stubs/module-preset-api-request.stub',
        'stubs/module-preset-api-resource.stub',
        'stubs/module-preset-api-route.stub',
        'stubs/module-preset-api-test.stub',
        'stubs/module-preset-empty.stub',
        'stubs/module-preset-translations.stub',
        'stubs/module-preset-view.stub',
        'stubs/module-preset-web-controller.stub',
        'stubs/module-preset-web-route.stub',
        'stubs/module-preset-web-test.stub',
        'stubs/module-test.feature.pest.stub',
        'stubs/module-test.feature.phpunit.stub',
        'stubs/module-test.unit.pest.stub',
        'stubs/module-test.unit.phpunit.stub',
        'stubs/module-view-test.pest.stub',
        'stubs/module-view-test.phpunit.stub',
    ];

    /** @var list<string> */
    public const EXCLUDED_TREES = [
        '.github/',
        'benchmarks/',
        'tests/',
        'tools/',
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
