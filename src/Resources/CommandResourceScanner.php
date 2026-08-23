<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleResourceDiscoveryFailed;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CommandResourceScanner
{
    /** @return list<array{class: string, path: string}> */
    public function scan(DiscoveredModule $module, string $path, bool $recursive): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = $recursive ? $this->recursiveFiles($path) : $this->directFiles($module, $path);
        $commands = [];

        foreach ($files as $file) {
            if (! $this->isConcreteClass($module, $file)) {
                continue;
            }

            $relative = substr($file, strlen(rtrim($path, DIRECTORY_SEPARATOR)) + 1);
            $relativeClass = substr($relative, 0, -4);
            $commandClass = $module->namespace().'\\Console\\Commands\\'
                .str_replace(DIRECTORY_SEPARATOR, '\\', $relativeClass);
            $commands[] = ['class' => $commandClass, 'path' => $file];
        }

        return $commands;
    }

    /** @return list<string> */
    private function directFiles(DiscoveredModule $module, string $path): array
    {
        $matches = glob($path.DIRECTORY_SEPARATOR.'*.php');

        if ($matches === false) {
            throw ModuleResourceDiscoveryFailed::commandScanFailed($module->moduleClass(), $path);
        }

        $files = array_values(array_filter($matches, 'is_file'));
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return list<string> */
    private function recursiveFiles(string $path): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function isConcreteClass(DiscoveredModule $module, string $path): bool
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw ModuleResourceDiscoveryFailed::commandScanFailed($module->moduleClass(), $path);
        }

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError) {
            throw ModuleResourceDiscoveryFailed::invalidCommand(
                $module->moduleClass(),
                pathinfo($path, PATHINFO_FILENAME),
                $path,
            );
        }

        $abstract = false;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_ABSTRACT) {
                $abstract = true;
            }

            if (in_array($token[0], [T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                return false;
            }

            if ($token[0] === T_CLASS) {
                return ! $abstract;
            }
        }

        return false;
    }
}
