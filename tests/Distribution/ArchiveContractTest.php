<?php

declare(strict_types=1);

namespace Tests\Distribution;

use PharData;
use PHPUnit\Framework\TestCase;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ArchiveContractTest extends TestCase
{
    private string $archivePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->archivePath = sys_get_temp_dir().'/moduark-distribution-'.bin2hex(random_bytes(8)).'.tar';
    }

    protected function tearDown(): void
    {
        if (is_file($this->archivePath)) {
            unlink($this->archivePath);
        }

        parent::tearDown();
    }

    public function test_git_archive_contains_runtime_files_without_development_trees(): void
    {
        $entries = $this->archiveEntries();

        foreach (PackageArchiveContract::REQUIRED_FILES as $required) {
            self::assertContains($required, $entries, "Distribution archive is missing [{$required}].");
        }

        foreach (PackageArchiveContract::EXCLUDED_TREES as $excluded) {
            self::assertFalse(
                $this->containsPrefix($entries, $excluded),
                "Distribution archive contains development tree [{$excluded}].",
            );
        }

        foreach (PackageArchiveContract::EXCLUDED_FILES as $excluded) {
            self::assertNotContains(
                $excluded,
                $entries,
                "Distribution archive contains development file [{$excluded}].",
            );
        }
    }

    /** @return list<string> */
    private function archiveEntries(): array
    {
        $this->createArchive();
        $archivePath = realpath($this->archivePath);

        if ($archivePath === false) {
            throw new RuntimeException('Distribution archive was not created.');
        }

        $prefix = 'phar://'.str_replace('\\', '/', $archivePath).'/';
        $entries = [];

        foreach (new RecursiveIteratorIterator(new PharData($archivePath)) as $file) {
            if (! $file instanceof SplFileInfo) {
                throw new RuntimeException('Archive iterator returned a non-file entry.');
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (! str_starts_with($path, $prefix)) {
                throw new RuntimeException("Unexpected archive entry path [{$path}].");
            }

            $entries[] = substr($path, strlen($prefix));
        }

        sort($entries, SORT_STRING);

        return $entries;
    }

    private function createArchive(): void
    {
        $errorStream = fopen('php://temp', 'w+');

        if ($errorStream === false) {
            throw new RuntimeException('Unable to open the archive error stream.');
        }

        $process = proc_open(
            [
                'git',
                'archive',
                '--format=tar',
                '--worktree-attributes',
                '--output='.$this->archivePath,
                'HEAD',
            ],
            [
                1 => ['pipe', 'w'],
                2 => $errorStream,
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (! is_resource($process)) {
            fclose($errorStream);

            throw new RuntimeException('Unable to start git archive.');
        }

        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);
        rewind($errorStream);
        $error = stream_get_contents($errorStream);
        fclose($errorStream);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'git archive failed with exit code %d: %s',
                $exitCode,
                trim($error === false ? '' : $error),
            ));
        }
    }

    /** @param list<string> $entries */
    private function containsPrefix(array $entries, string $prefix): bool
    {
        foreach ($entries as $entry) {
            if (str_starts_with($entry, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
