<?php

declare(strict_types=1);

namespace Tests\Distribution;

use PHPUnit\Framework\TestCase;

final class BoostSkillContractTest extends TestCase
{
    private const SKILL_PATH = 'resources/boost/skills/moduark-development/SKILL.md';

    /** @var list<string> */
    private const REFERENCES = [
        'references/adoption-and-levels.md',
        'references/diagnostics-and-debt.md',
        'references/inspection-and-upgrades.md',
    ];

    public function test_skill_has_portable_frontmatter_and_trigger_description(): void
    {
        $contents = $this->read(self::SKILL_PATH);

        self::assertSame(1, preg_match('/\A---\n(?<frontmatter>.*?)\n---\n/s', $contents, $matches));

        if (! isset($matches['frontmatter'])) {
            self::fail('Skill frontmatter was not captured.');
        }

        $frontmatter = [];

        foreach (explode("\n", $matches['frontmatter']) as $line) {
            $parts = explode(': ', $line, 2);

            if (count($parts) !== 2) {
                self::fail("Invalid Skill frontmatter line [{$line}].");
            }

            [$key, $value] = $parts;
            self::assertArrayNotHasKey($key, $frontmatter);
            $frontmatter[$key] = $value;
        }

        self::assertSame(['name', 'description'], array_keys($frontmatter));
        self::assertSame('moduark-development', $frontmatter['name']);
        self::assertStringContainsString('cluion/moduark', $frontmatter['description']);
        self::assertStringContainsString('Use when', $frontmatter['description']);
    }

    public function test_skill_routes_to_every_focused_reference(): void
    {
        $contents = $this->read(self::SKILL_PATH);
        preg_match_all('/\[[^]]+]\((references\/[^)]+\.md)\)/', $contents, $matches);

        self::assertSame(self::REFERENCES, $matches[1]);

        foreach (self::REFERENCES as $reference) {
            self::assertFileExists($this->root().'/'.dirname(self::SKILL_PATH).'/'.$reference);
        }
    }

    public function test_skill_preserves_the_cli_safety_contract(): void
    {
        $contents = $this->read(self::SKILL_PATH);

        foreach ([
            'git status --short',
            'composer show cluion/moduark --path',
            'moduark:check --format=json',
            'Exit `0`',
            'Exit `1`',
            'Exit `2`',
            '`complete: false`',
            '`status: incomplete`',
            'Do not disable rules',
            'global ignore',
        ] as $required) {
            self::assertStringContainsString($required, $contents, "Skill is missing safety contract [{$required}].");
        }
    }

    public function test_distribution_contract_includes_the_complete_skill(): void
    {
        $expected = [self::SKILL_PATH];

        foreach (self::REFERENCES as $reference) {
            $expected[] = dirname(self::SKILL_PATH).'/'.$reference;
        }

        foreach ($expected as $path) {
            self::assertContains($path, PackageArchiveContract::REQUIRED_FILES);
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root().'/'.$path);

        self::assertNotFalse($contents, "Unable to read Skill file [{$path}].");

        return $contents;
    }
}
