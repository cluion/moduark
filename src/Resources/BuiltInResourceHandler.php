<?php

declare(strict_types=1);

namespace Cluion\Moduark\Resources;

use Cluion\Moduark\Exceptions\ModuleResourceDiscoveryFailed;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Routing\Router;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Translation\Translator;
use ReflectionClass;

final readonly class BuiltInResourceHandler implements ResourceHandler
{
    public function __construct(private string $plugin)
    {
    }

    public function phase(): ResourcePhase
    {
        return $this->plugin === 'config' ? ResourcePhase::Register : ResourcePhase::Boot;
    }

    public function handle(ResourceDescriptor $resource, ResourceRuntime $runtime): void
    {
        $application = $runtime->application();

        if ($runtime->conventionsManagedExternally()
            && ($resource->attributes()['conventional'] ?? false) === true) {
            return;
        }

        if ($this->plugin === 'routes') {
            if (! $runtime->routesAreCached() && $resource->sourcePath() !== null) {
                $group = $resource->attributes()['group'] ?? [];

                if (is_array($group)) {
                    $application->make(Router::class)->group($group, $resource->sourcePath());
                }
            }

            return;
        }

        if ($this->plugin === 'config' && $resource->sourcePath() !== null) {
            $key = $resource->attributes()['key'] ?? null;
            $values = $resource->attributes()['values'] ?? null;

            if (is_string($key) && is_array($values)) {
                $runtime->mergeConfiguration(
                    ResourceData::normalizeMap(
                        $values,
                        $resource->moduleClass().'.config.'.$key,
                    ),
                    $key,
                    $resource->moduleClass(),
                );

                if (($resource->attributes()['publish'] ?? false) === true) {
                    $runtime->publishResource(
                        $resource->sourcePath(),
                        $application->configPath(basename($resource->sourcePath())),
                        'moduark-config',
                    );
                }
            }

            return;
        }

        if ($this->plugin === 'views' && $resource->sourcePath() !== null && $resource->runtimeNamespace() !== null) {
            $application->make(ViewFactory::class)->addNamespace(
                $resource->runtimeNamespace(),
                $resource->sourcePath(),
            );

            return;
        }

        if ($this->plugin === 'translations' && $resource->sourcePath() !== null && $resource->runtimeNamespace() !== null) {
            $application->make(Translator::class)->addNamespace(
                $resource->runtimeNamespace(),
                $resource->sourcePath(),
            );

            return;
        }

        if ($this->plugin === 'migrations' && $resource->sourcePath() !== null) {
            $application->make(Migrator::class)->path($resource->sourcePath());

            return;
        }

        if ($this->plugin === 'policies') {
            $subject = $resource->attributes()['subject'] ?? null;
            $handler = $resource->attributes()['handler'] ?? null;

            if (is_string($subject) && is_string($handler)) {
                $application->make(Gate::class)->policy($subject, $handler);
            }

            return;
        }

        if ($this->plugin === 'listeners') {
            $event = $resource->attributes()['event'] ?? null;
            $listener = $resource->attributes()['listener'] ?? null;

            if (is_string($event) && is_string($listener)) {
                $application->make(Dispatcher::class)->listen($event, $listener);
            }

            return;
        }

        if ($this->plugin === 'components' && $resource->runtimeNamespace() !== null) {
            $prefix = $resource->attributes()['prefix'] ?? null;

            if (is_string($prefix)) {
                $application->make(BladeCompiler::class)->componentNamespace(
                    $resource->runtimeNamespace(),
                    $prefix,
                );
            }

            return;
        }

        if ($this->plugin === 'assets'
            && ($resource->attributes()['type'] ?? null) === 'public'
            && $resource->sourcePath() !== null) {
            $publishTo = $resource->attributes()['publish_to'] ?? null;

            if (is_string($publishTo)) {
                $runtime->publishResource(
                    $resource->sourcePath(),
                    $application->publicPath($publishTo),
                    'moduark-assets',
                );
            }

            return;
        }

        if ($this->plugin === 'commands') {
            if (! $runtime->runningInConsole()) {
                return;
            }

            $class = $resource->attributes()['class'] ?? null;

            if (! is_string($class) || $resource->sourcePath() === null || ! class_exists($class)) {
                throw ModuleResourceDiscoveryFailed::invalidCommand(
                    $resource->moduleClass(),
                    is_string($class) ? $class : '[invalid]',
                    $resource->sourcePath() ?? '[unknown]',
                );
            }

            $reflection = new ReflectionClass($class);
            $autoloaded = $reflection->getFileName();
            $expectedPath = realpath($resource->sourcePath());
            $autoloadedPath = is_string($autoloaded) ? realpath($autoloaded) : false;

            if ($expectedPath === false || $autoloadedPath !== $expectedPath) {
                throw ModuleResourceDiscoveryFailed::commandSourceMismatch(
                    $resource->moduleClass(),
                    $class,
                    $resource->sourcePath(),
                    is_string($autoloaded) ? $autoloaded : '[internal]',
                );
            }

            if (! is_a($class, Command::class, true) || ! $reflection->isInstantiable()) {
                throw ModuleResourceDiscoveryFailed::invalidCommand(
                    $resource->moduleClass(),
                    $class,
                    $resource->sourcePath(),
                );
            }

            /** @var class-string<Command> $class */
            Artisan::starting(static function (Artisan $artisan) use ($class): void {
                $artisan->resolveCommands([$class]);
            });
        }
    }
}
