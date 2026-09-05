<?php

declare(strict_types=1);

namespace Tests\Architecture;

use JsonException;
use PHPUnit\Framework\TestCase;

final class ReleasePolicyContractTest extends TestCase
{
    public function test_release_states_are_separate_and_explicitly_authorized(): void
    {
        $policy = $this->contents('docs/releases.md');

        foreach ([
            'Local validation',
            'Exact-commit CI',
            'Annotated tag',
            'GitHub Release',
            'Packagist visibility',
            'Published-dist acceptance',
            'Each mutating external stage requires separate explicit',
            'A local pass is not an exact-commit CI result.',
        ] as $contract) {
            self::assertStringContainsString($contract, $policy);
        }
    }

    /** @throws JsonException */
    public function test_local_release_commands_exist_in_composer_workflow(): void
    {
        $policy = $this->contents('docs/releases.md');

        /** @var array{scripts: array<string, mixed>} $composer */
        $composer = json_decode(
            $this->contents('composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ([
            'verify',
            'test:dependencies',
            'test:lowest',
            'test:distribution',
            'test:installation',
            'test:interop',
        ] as $script) {
            self::assertArrayHasKey($script, $composer['scripts']);
            self::assertStringContainsString("composer {$script}", $policy);
        }

        foreach ([
            'composer validate --strict',
            'composer audit --locked',
            'composer test:installation -- --boost',
            'composer test:installation -- --package="${MODUARK_RELEASE_VERSION}"',
            'composer test:interop -- --package="${MODUARK_RELEASE_VERSION}"',
        ] as $command) {
            self::assertStringContainsString($command, $policy);
        }
    }

    public function test_exact_commit_and_public_artifact_verification_cannot_be_skipped(): void
    {
        $policy = $this->contents('docs/releases.md');

        foreach ([
            '--commit "${MODUARK_RELEASE_SHA}"',
            'git tag -a "${MODUARK_RELEASE_TAG}" "${MODUARK_RELEASE_SHA}"',
            'git rev-parse "${MODUARK_RELEASE_TAG}^{commit}"',
            'composer show cluion/moduark "${MODUARK_RELEASE_VERSION}"',
            'Both references must equal `MODUARK_RELEASE_SHA`.',
            'Do not move, replace, or delete a public tag.',
        ] as $contract) {
            self::assertStringContainsString($contract, $policy);
        }
    }

    public function test_rc_and_artifact_claims_match_current_repository_capabilities(): void
    {
        $policy = $this->contents('docs/releases.md');

        foreach ([
            'At least one `1.0.0-rc.*` release is required before `1.0.0`.',
            'Level 3 remains Preview',
            'currently publishes custom assets.',
            'Do not claim checksums, SBOMs, provenance, or attestations',
            'A stable release reruns every gate',
        ] as $contract) {
            self::assertStringContainsString($contract, $policy);
        }
    }

    public function test_current_minor_preparation_documents_are_version_aligned(): void
    {
        foreach ([
            'CHANGELOG.md',
            'README.md',
            'UPGRADING.md',
            'docs/adoption.md',
            'docs/stability.md',
        ] as $path) {
            self::assertStringContainsString('1.0.0', $this->contents($path));
        }

        $changelog = $this->contents('CHANGELOG.md');
        self::assertStringContainsString('## [Unreleased]', $changelog);
        self::assertStringContainsString('## [1.3.0-rc.1] - 2026-09-05', $changelog);
        self::assertStringContainsString('Laravel 13 + `nwidart/laravel-modules`', $changelog);
        self::assertStringContainsString('## [1.2.0]', $changelog);
        self::assertStringContainsString('## [1.1.0]', $changelog);
        self::assertStringContainsString('## [1.0.1]', $changelog);
        self::assertStringContainsString('## [1.0.0]', $changelog);
        self::assertStringContainsString('## [1.0.0-rc.2]', $changelog);
        self::assertStringContainsString('## [1.0.0-rc.1]', $changelog);
        self::assertStringContainsString(
            '[Unreleased]: https://github.com/cluion/moduark/compare/v1.3.0-rc.1...HEAD',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.3.0-rc.1]: https://github.com/cluion/moduark/compare/v1.2.0...v1.3.0-rc.1',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.2.0]: https://github.com/cluion/moduark/compare/v1.1.0...v1.2.0',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.1.0]: https://github.com/cluion/moduark/compare/v1.0.1...v1.1.0',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.0.1]: https://github.com/cluion/moduark/compare/v1.0.0...v1.0.1',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.0.0]: https://github.com/cluion/moduark/compare/v1.0.0-rc.2...v1.0.0',
            $changelog,
        );
        self::assertStringContainsString(
            '[1.0.0-rc.2]: https://github.com/cluion/moduark/compare/v1.0.0-rc.1...v1.0.0-rc.2',
            $changelog,
        );

        $installationDocs = '';
        foreach (['README.md', 'docs/adoption.md'] as $path) {
            $contents = $this->contents($path);
            $installationDocs .= $contents;

            self::assertStringContainsString(
                'composer require cluion/moduark:^1.2',
                $contents,
            );
        }

        self::assertStringNotContainsString(
            'composer require cluion/moduark:1.0.0-rc.2',
            $installationDocs,
        );

        self::assertStringNotContainsString(
            'composer require cluion/moduark:^0.5@beta',
            $installationDocs,
        );
        self::assertStringNotContainsString(
            'The `0.6.x` development line includes',
            $installationDocs,
        );

        foreach ([
            'CHANGELOG.md',
            'README.md',
            'SECURITY.md',
            'UPGRADING.md',
            'docs/stability.md',
        ] as $path) {
            self::assertStringContainsString('1.3.0-rc.1', $this->contents($path));
        }

        self::assertStringContainsString(
            'composer require cluion/moduark:1.3.0-rc.1',
            $this->contents('README.md'),
        );
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

        self::assertNotFalse($contents, "Unable to read [{$path}].");

        return $contents;
    }
}
