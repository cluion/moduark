<?php

declare(strict_types=1);

namespace Cluion\Moduark\Extraction;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use Composer\Autoload\ClassLoader;
use ReflectionClass;
use ReflectionException;

final readonly class PortableRuntimeGate
{
    /** @var list<string> */
    private const BUILT_IN_PLUGINS = [
        'assets',
        'commands',
        'components',
        'config',
        'events',
        'extensions',
        'factories',
        'listeners',
        'migrations',
        'policies',
        'providers',
        'routes',
        'seeders',
        'tests',
        'translations',
        'views',
    ];

    /** @var array<string, list<string>> */
    private const CLASS_ATTRIBUTES = [
        'commands' => ['class'],
        'events' => ['class'],
        'listeners' => ['event', 'listener'],
        'policies' => ['subject', 'handler'],
        'providers' => ['class'],
        'seeders' => ['class'],
    ];

    public function __construct(
        private ResourceManifest $resources,
        private ModuleRegistry $registry,
        private ModulesConfig $configuration,
        private string $applicationVendorPath,
        private ProviderBindingScanner $bindings,
    ) {
    }

    /** @return list<ExtractabilityCheck> */
    public function checks(DiscoveredModule $module, ModuleDescriptor $metadata): array
    {
        return [
            $this->pluginContract($module),
            $this->namespaces($module),
            $this->collisions($module),
            $this->publishTargets($module),
            $this->containerBindings($module, $metadata),
        ];
    }

    private function pluginContract(DiscoveredModule $module): ExtractabilityCheck
    {
        $plugins = [];
        $blockers = [];

        foreach ($this->resources->forModule($module->moduleClass()) as $resource) {
            $plugin = $resource->plugin();
            $plugins[] = $plugin;

            if (! in_array($plugin, self::BUILT_IN_PLUGINS, true)) {
                $blockers[] = 'unsupported_plugin='.$plugin.':'.$resource->identity();

                continue;
            }

            foreach (self::CLASS_ATTRIBUTES[$plugin] ?? [] as $attribute) {
                $value = $resource->attributes()[$attribute] ?? null;

                if (! is_string($value) || $value === '') {
                    $blockers[] = $plugin.':'.$resource->identity().':'.$attribute.'=[unresolved]';

                    continue;
                }

                $issue = $this->classIssue($value);

                if ($issue !== null) {
                    $blockers[] = $plugin.':'.$resource->identity().':'.$attribute.'='.$issue;
                }
            }
        }

        if ($blockers !== []) {
            return $this->blocked(
                'MOD-EXTRACT-PLUGIN-001',
                'resource_plugin',
                'A resource plugin or class attribute cannot be proven portable.',
                $blockers,
            );
        }

        $plugins = array_values(array_unique($plugins));
        sort($plugins, SORT_STRING);

        return $this->passed(
            'MOD-EXTRACT-PLUGIN-001',
            'resource_plugin',
            'Every resource uses a supported portable plugin contract.',
            ['plugins='.($plugins === [] ? 'none' : implode(',', $plugins))],
        );
    }

    private function namespaces(DiscoveredModule $module): ExtractabilityCheck
    {
        $blockers = [];
        $evidence = [];

        foreach ($this->resources->forModule($module->moduleClass()) as $resource) {
            $plugin = $resource->plugin();

            if (in_array($plugin, ['config', 'views', 'translations'], true)) {
                $namespace = $resource->runtimeNamespace();

                if ($namespace === null || ! $this->moduleScoped($namespace, $module->name())) {
                    $blockers[] = $plugin.':'.$resource->identity().'='.($namespace ?? '[missing]');
                } else {
                    $evidence[] = $plugin.':'.$resource->identity().'='.$namespace;
                }
            }

            if ($plugin === 'components') {
                $namespace = $resource->runtimeNamespace();
                $prefix = $resource->attributes()['prefix'] ?? null;

                if ($namespace === null || ! $this->moduleScoped($namespace, $module->name())) {
                    $blockers[] = 'components:namespace='.($namespace ?? '[missing]');
                } else {
                    $evidence[] = 'components:namespace='.$namespace;
                }

                if (! is_string($prefix) || ! $this->moduleScoped($prefix, $module->name())) {
                    $blockers[] = 'components:prefix='.(is_string($prefix) ? $prefix : '[missing]');
                } else {
                    $evidence[] = 'components:prefix='.$prefix;
                }
            }

            if ($plugin === 'routes') {
                $group = $resource->attributes()['group'] ?? [];
                $routeNamespace = is_array($group) ? ($group['namespace'] ?? null) : null;
                $routeName = is_array($group) ? ($group['as'] ?? null) : null;

                if ($routeNamespace !== null) {
                    if (! is_string($routeNamespace)
                        || ! $this->classNamespaceOwnedBy($routeNamespace, $module->namespace())) {
                        $blockers[] = 'routes:'.$resource->identity().':namespace='.(
                            is_string($routeNamespace) ? $routeNamespace : '[invalid]'
                        );
                    } else {
                        $evidence[] = 'routes:'.$resource->identity().':namespace='.$routeNamespace;
                    }
                }

                if ($routeName !== null) {
                    if (! is_string($routeName) || ! $this->moduleScoped($routeName, $module->name())) {
                        $blockers[] = 'routes:'.$resource->identity().':as='.(
                            is_string($routeName) ? $routeName : '[invalid]'
                        );
                    } else {
                        $evidence[] = 'routes:'.$resource->identity().':as='.$routeName;
                    }
                }
            }
        }

        if ($blockers !== []) {
            return $this->blocked(
                'MOD-EXTRACT-NAMESPACE-001',
                'resource_namespace',
                'A runtime resource namespace is not scoped to the Module.',
                $blockers,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-NAMESPACE-001',
            'resource_namespace',
            'Every declared runtime resource namespace is Module-scoped.',
            $evidence === [] ? ['runtime_namespaces=none'] : $evidence,
        );
    }

    private function collisions(DiscoveredModule $module): ExtractabilityCheck
    {
        $blockers = [];

        foreach ($this->resources->collisions() as $collision) {
            $involvesModule = false;

            foreach ($collision['resources'] as $resource) {
                if (($resource['module'] ?? null) === $module->moduleClass()) {
                    $involvesModule = true;
                    break;
                }
            }

            if ($involvesModule) {
                $blockers[] = $collision['plugin'].'='.$collision['collision_key'].':'.json_encode(
                    $collision['resources'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                );
            }
        }

        if ($blockers !== []) {
            return $this->blocked(
                'MOD-EXTRACT-COLLISION-001',
                'resource_collision',
                'The Module participates in an active resource-manifest collision.',
                $blockers,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-COLLISION-001',
            'resource_collision',
            'The Module has no active resource-manifest collisions.',
            ['collisions=none'],
        );
    }

    private function publishTargets(DiscoveredModule $module): ExtractabilityCheck
    {
        $targets = [];

        foreach ($this->resources->all() as $resource) {
            $target = $this->publishTarget($resource);

            if ($target !== null) {
                $targets[strtolower($target)][] = $resource;
            }
        }

        $blockers = [];
        $evidence = [];

        foreach ($this->resources->forModule($module->moduleClass()) as $resource) {
            if ($resource->plugin() === 'assets'
                && ($resource->attributes()['type'] ?? 'input') === 'input') {
                $evidence[] = 'input='.$resource->sourcePath();
                continue;
            }

            $target = $this->publishTarget($resource);

            if ($target === null) {
                continue;
            }

            $relative = substr($target, strpos($target, ':') + 1);

            if (! $this->safeRelativeTarget($relative)) {
                $blockers[] = 'unsafe_target='.$target;
                continue;
            }

            if (count($targets[strtolower($target)] ?? []) > 1) {
                $blockers[] = 'collision='.$target;
                continue;
            }

            $evidence[] = 'target='.$target;
        }

        if ($blockers !== []) {
            return $this->blocked(
                'MOD-EXTRACT-PUBLISH-001',
                'publish_target',
                'A config or asset publish target is unsafe or collides with another active Module.',
                $blockers,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-PUBLISH-001',
            'publish_target',
            'Asset inputs and publish targets are portable at manifest level.',
            $evidence === [] ? ['publish_targets=none'] : $evidence,
        );
    }

    private function containerBindings(
        DiscoveredModule $module,
        ModuleDescriptor $metadata,
    ): ExtractabilityCheck {
        $blockers = [];
        $evidence = [];

        foreach ($metadata->providers() as $provider) {
            foreach ($this->bindings->scan($provider) as $binding) {
                $rendered = $this->bindingEvidence($binding);
                $issues = [];

                if (! in_array($binding['receiver'], ['provider_app', 'app_helper', 'facade'], true)) {
                    $issues[] = 'receiver='.$binding['receiver'];
                }

                if ($binding['method'] === 'when') {
                    $issues[] = 'contextual_binding';
                }

                $abstractIssue = $this->bindingOperandIssue(
                    $binding['abstract'],
                    $module->name(),
                    false,
                );
                $concreteIssue = $this->bindingOperandIssue(
                    $binding['concrete'],
                    $module->name(),
                    true,
                );

                if ($abstractIssue !== null) {
                    $issues[] = 'abstract='.$abstractIssue;
                }

                if ($concreteIssue !== null) {
                    $issues[] = 'concrete='.$concreteIssue;
                }

                if ($issues === []) {
                    $evidence[] = $rendered;
                } else {
                    $blockers[] = implode(',', $issues).':'.$rendered;
                }
            }
        }

        if ($blockers !== []) {
            return $this->blocked(
                'MOD-EXTRACT-BINDING-001',
                'container_binding',
                'A provider container binding cannot be proven portable.',
                $blockers,
            );
        }

        return $this->passed(
            'MOD-EXTRACT-BINDING-001',
            'container_binding',
            'Every detected provider container binding is portable.',
            $evidence === [] ? ['bindings=none'] : $evidence,
        );
    }

    /**
     * @param array{kind: string, value: ?string} $operand
     */
    private function bindingOperandIssue(array $operand, string $module, bool $allowValue): ?string
    {
        if ($allowValue && in_array($operand['kind'], ['factory', 'null', 'same', 'scalar'], true)) {
            return null;
        }

        if ($operand['kind'] === 'class' && $operand['value'] !== null) {
            return $this->classIssue($operand['value']);
        }

        if ($operand['kind'] === 'string' && $operand['value'] !== null) {
            if ($this->symbolExists($operand['value'])) {
                return $this->classIssue($operand['value']);
            }

            return $this->moduleScoped($operand['value'], $module)
                ? null
                : 'unscoped_string:'.$operand['value'];
        }

        return $operand['kind'].':'.($operand['value'] ?? '[none]');
    }

    private function classIssue(string $class): ?string
    {
        if (! $this->symbolExists($class)) {
            return 'unresolved_class:'.$class;
        }

        $file = $this->classFile($class);

        if ($file === null) {
            return null;
        }

        $moduleRoots = array_map(
            fn (DiscoveredModule $candidate): string => $this->moduleRoot($candidate),
            $this->registry->all(),
        );

        if ($this->withinAny($file, $moduleRoots) || $this->withinAny($file, $this->externalRoots())) {
            return null;
        }

        return 'application_global_class:'.$class.'='.$file;
    }

    /** @phpstan-assert-if-true class-string $class */
    private function symbolExists(string $class): bool
    {
        return class_exists($class)
            || interface_exists($class)
            || trait_exists($class)
            || enum_exists($class);
    }

    /**
     * @param array{
     *     provider: class-string,
     *     method: string,
     *     line: int,
     *     receiver: string,
     *     abstract: array{kind: string, value: ?string},
     *     concrete: array{kind: string, value: ?string}
     * } $binding
     */
    private function bindingEvidence(array $binding): string
    {
        return $binding['provider'].':'.$binding['line']
            .' method='.$binding['method']
            .' receiver='.$binding['receiver']
            .' abstract='.$binding['abstract']['kind'].':'.($binding['abstract']['value'] ?? '[none]')
            .' concrete='.$binding['concrete']['kind'].':'.($binding['concrete']['value'] ?? '[none]');
    }

    private function publishTarget(ResourceDescriptor $resource): ?string
    {
        if ($resource->plugin() === 'config'
            && ($resource->attributes()['publish'] ?? false) === true) {
            $source = $resource->sourcePath();

            return $source === null ? 'config:' : 'config:'.basename($source);
        }

        if ($resource->plugin() === 'assets'
            && ($resource->attributes()['type'] ?? null) === 'public') {
            $target = $resource->attributes()['publish_to'] ?? null;

            return is_string($target) ? 'public:'.$target : 'public:';
        }

        return null;
    }

    private function safeRelativeTarget(string $target): bool
    {
        if ($target === ''
            || trim($target) !== $target
            || str_contains($target, "\0")
            || str_contains($target, '\\')
            || str_starts_with($target, '/')
            || preg_match('/\A[A-Za-z]:/', $target) === 1) {
            return false;
        }

        foreach (explode('/', $target) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function moduleScoped(string $identity, string $module): bool
    {
        $normalized = preg_replace('~[^a-z0-9]+~', ' ', strtolower($identity));

        if ($normalized === null) {
            return false;
        }

        return in_array(
            strtolower($module),
            preg_split('~\s+~', trim($normalized)) ?: [],
            true,
        );
    }

    private function classNamespaceOwnedBy(string $namespace, string $moduleNamespace): bool
    {
        $namespace = trim($namespace, '\\');
        $moduleNamespace = trim($moduleNamespace, '\\');

        return strcasecmp($namespace, $moduleNamespace) === 0
            || str_starts_with(strtolower($namespace), strtolower($moduleNamespace).'\\');
    }

    /** @return list<string> */
    private function externalRoots(): array
    {
        $roots = [$this->applicationVendorPath];
        $loaderFile = (new ReflectionClass(ClassLoader::class))->getFileName();

        if (is_string($loaderFile)) {
            $roots[] = dirname($loaderFile, 2);
        }

        return array_values(array_unique($roots));
    }

    private function moduleRoot(DiscoveredModule $module): string
    {
        $sourceRoot = dirname($module->path());
        $base = rtrim($this->configuration->path(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$module->name();
        $nwidartEntry = $base.DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.$module->name().'Module.php';

        return $this->samePath($module->path(), $nwidartEntry)
            ? dirname($sourceRoot)
            : $sourceRoot;
    }

    /** @param class-string $class */
    private function classFile(string $class): ?string
    {
        try {
            $file = (new ReflectionClass($class))->getFileName();
        } catch (ReflectionException) {
            return null;
        }

        return is_string($file) ? $file : null;
    }

    /** @param list<string> $roots */
    private function withinAny(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($this->within($path, $root)) {
                return true;
            }
        }

        return false;
    }

    private function within(string $path, string $root): bool
    {
        $path = $this->normalizePath($path);
        $root = rtrim($this->normalizePath($root), '/');

        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function samePath(string $left, string $right): bool
    {
        $left = $this->normalizePath($left);
        $right = $this->normalizePath($right);

        return PHP_OS_FAMILY === 'Windows'
            ? strtolower($left) === strtolower($right)
            : $left === $right;
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return str_replace('\\', '/', $realPath === false ? $path : $realPath);
    }

    /** @param list<string> $evidence */
    private function passed(
        string $code,
        string $category,
        string $message,
        array $evidence,
    ): ExtractabilityCheck {
        return new ExtractabilityCheck(
            $code,
            $category,
            ExtractabilityCheck::PASSED,
            $message,
            $evidence,
        );
    }

    /** @param list<string> $evidence */
    private function blocked(
        string $code,
        string $category,
        string $message,
        array $evidence,
    ): ExtractabilityCheck {
        return new ExtractabilityCheck(
            $code,
            $category,
            ExtractabilityCheck::BLOCKED,
            $message,
            $evidence,
        );
    }
}
