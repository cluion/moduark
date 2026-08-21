<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\ModuleResourceDiscoveryFailed;
use Illuminate\Console\Command;
use ReflectionClass;

final class ModuleResourceDiscoverer
{
    public function discover(DiscoveredModule $module, bool $includeCommands): ModuleResources
    {
        $rootPath = dirname($module->path());
        $routePaths = [];

        foreach (['web.php', 'api.php'] as $routeFile) {
            $path = $rootPath.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.$routeFile;

            if (is_file($path)) {
                $routePaths[] = $path;
            }
        }

        return new ModuleResources(
            strtolower($module->name()),
            $routePaths,
            $this->existingDirectory($rootPath.'/resources/views'),
            $this->existingDirectory($rootPath.'/resources/lang'),
            $this->existingDirectory($rootPath.'/Database/Migrations'),
            $includeCommands
                ? $this->commandClasses($module, $rootPath.'/Console/Commands')
                : [],
        );
    }

    private function existingDirectory(string $path): ?string
    {
        return is_dir($path) ? $path : null;
    }

    /**
     * @return list<class-string<Command>>
     */
    private function commandClasses(DiscoveredModule $module, string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $matches = glob($path.DIRECTORY_SEPARATOR.'*.php');

        if ($matches === false) {
            throw ModuleResourceDiscoveryFailed::commandScanFailed($module->moduleClass(), $path);
        }

        $files = array_values(array_filter($matches, 'is_file'));
        sort($files, SORT_STRING);
        $commands = [];

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $commandClass = $module->namespace().'\\Console\\Commands\\'.$className;

            if (! class_exists($commandClass)
                && ! interface_exists($commandClass)
                && ! trait_exists($commandClass)
                && ! enum_exists($commandClass)) {
                throw ModuleResourceDiscoveryFailed::invalidCommand(
                    $module->moduleClass(),
                    $commandClass,
                    $file,
                );
            }

            $reflection = new ReflectionClass($commandClass);
            $autoloadedPath = $reflection->getFileName();
            $expectedRealPath = realpath($file);
            $autoloadedRealPath = is_string($autoloadedPath) ? realpath($autoloadedPath) : false;

            if ($expectedRealPath === false || $autoloadedRealPath !== $expectedRealPath) {
                throw ModuleResourceDiscoveryFailed::commandSourceMismatch(
                    $module->moduleClass(),
                    $commandClass,
                    $file,
                    is_string($autoloadedPath) ? $autoloadedPath : '[internal]',
                );
            }

            if ($reflection->isInterface()
                || $reflection->isTrait()
                || $reflection->isEnum()
                || $reflection->isAbstract()) {
                continue;
            }

            if (! is_a($commandClass, Command::class, true) || ! $reflection->isInstantiable()) {
                throw ModuleResourceDiscoveryFailed::invalidCommand(
                    $module->moduleClass(),
                    $commandClass,
                    $file,
                );
            }

            /** @var class-string<Command> $commandClass */
            $commands[] = $commandClass;
        }

        return $commands;
    }
}
