<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DocumentationLinksTest extends TestCase
{
    public function test_local_markdown_links_resolve_to_repository_files(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            $root.'/README.md',
            $root.'/UPGRADING.md',
        ];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/docs'),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertNotFalse($contents, "Unable to read documentation [{$file}].");
            preg_match_all('/\[[^]]+]\(([^)]+)\)/', $contents, $matches);

            foreach ($matches[1] as $target) {
                if (preg_match('/\A(?:https?:|mailto:|#)/', $target) === 1) {
                    continue;
                }

                $path = explode('#', rawurldecode($target), 2)[0];
                self::assertFileExists(
                    dirname($file).'/'.$path,
                    "Documentation link [{$target}] in [{$file}] does not resolve.",
                );
            }
        }
    }
}
