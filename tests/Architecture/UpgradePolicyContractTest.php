<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UpgradePolicyContractTest extends TestCase
{
    public function test_upgrade_guide_preserves_review_and_incomplete_analysis_safety(): void
    {
        $guide = $this->contents('UPGRADING.md');

        foreach ([
            '`1.0.0` has not been published',
            'php artisan module:check --format=json',
            'php artisan module:check --show-suppressions',
            'exit `2`, `complete: false`, or `status: incomplete`',
            'php artisan module:clear',
            'php artisan boost:install',
            '`module:baseline --force` captures every current unsuppressed violation',
            'must not be run automatically',
        ] as $contract) {
            self::assertStringContainsString($contract, $guide);
        }
    }

    public function test_beta_pair_identity_migration_is_explicit_and_non_automatic(): void
    {
        $guide = $this->contents('UPGRADING.md');

        foreach ([
            '`MOD-DEPENDENCY-002` identity migration',
            'one deterministic finding per ordered consumer /',
            '"consumer": "Order"',
            '"target": "User"',
            'Do not carry an old amplified count forward.',
            'php artisan module:baseline --prune',
            'Prune never adopts the newly visible pair diagnostic.',
        ] as $contract) {
            self::assertStringContainsString($contract, $guide);
        }
    }

    public function test_stable_deprecation_window_has_a_testable_minimum(): void
    {
        $policy = $this->contents('docs/stability.md');

        self::assertMatchesRegularExpression(
            '/at least one\s+released `1\.x` minor/',
            $policy,
        );

        foreach ([
            'next major',
            '`@deprecated` annotation',
            'keep the old and replacement paths under compatibility tests',
            'no migration may silently rewrite a baseline',
            'Internal APIs do not receive this window',
        ] as $contract) {
            self::assertStringContainsString($contract, $policy);
        }
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertNotFalse($contents, "Unable to read [{$path}].");

        return $contents;
    }
}
