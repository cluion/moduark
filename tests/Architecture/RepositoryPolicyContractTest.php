<?php

declare(strict_types=1);

namespace Tests\Architecture;

use JsonException;
use PHPUnit\Framework\TestCase;

final class RepositoryPolicyContractTest extends TestCase
{
    public function test_security_policy_names_the_verified_private_channel_and_current_support_line(): void
    {
        $policy = $this->contents('SECURITY.md');

        foreach ([
            'Latest `1.0` RC (`v1.0.0-rc.2`)',
            '`v0.5.0-beta.1` and earlier',
            '`1.0.0` stable has not been published',
            'https://github.com/cluion/moduark/security/advisories/new',
            'Do not open a public GitHub issue',
            'numeric response or remediation SLA',
        ] as $contract) {
            self::assertStringContainsString($contract, $policy);
        }

        self::assertStringNotContainsString('mailto:', $policy);
    }

    /** @throws JsonException */
    public function test_contribution_commands_exist_in_the_repository_workflow(): void
    {
        $guide = $this->contents('CONTRIBUTING.md');

        /** @var array{scripts: array<string, mixed>} $composer */
        $composer = json_decode(
            $this->contents('composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ([
            'test:unit',
            'test:feature',
            'test:dependencies',
            'test:distribution',
            'test:installation',
            'verify',
        ] as $script) {
            self::assertArrayHasKey($script, $composer['scripts']);
            self::assertStringContainsString("composer {$script}", $guide);
        }

        self::assertStringContainsString('authoritative compatibility result', $guide);
    }

    public function test_contribution_policy_keeps_private_corpus_data_and_local_plans_out_of_changes(): void
    {
        $guide = $this->contents('CONTRIBUTING.md');

        foreach ([
            '`tools/corpus/manifests/local-laravel.json`',
            'use a pinned',
            'never commit private source code, secrets, customer data',
            '`.internal/` is repository-local planning',
            'only to make a failing check pass.',
        ] as $contract) {
            self::assertStringContainsString($contract, $guide);
        }
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertNotFalse($contents, "Unable to read [{$path}].");

        return $contents;
    }
}
