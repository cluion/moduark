<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Extraction\ExtractabilityCheck;
use Cluion\Moduark\Extraction\PortableRuntimeGate;
use Cluion\Moduark\Extraction\ProviderBindingScanner;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Fixtures\Lifecycle\Activation\Modules\Foundation\FoundationModule;
use Tests\Fixtures\Lifecycle\Activation\Modules\Stripe\StripeModule;
use Workbench\App\Modules\Order\OrderModule;

final class PortableRuntimeGateTest extends TestCase
{
    public function test_portable_resources_and_bindings_pass_in_stable_order(): void
    {
        $order = $this->module('Order', OrderModule::class);
        $resources = new ResourceManifest([OrderModule::class], [
            new ResourceDescriptor(
                OrderModule::class,
                'config',
                'config/order.php',
                '/workspace/Order/config/order.php',
                'order-module',
                ['publish' => true],
                'order-module',
            ),
            new ResourceDescriptor(
                OrderModule::class,
                'views',
                'resources/views',
                '/workspace/Order/resources/views',
                'order',
                collisionKey: 'order',
            ),
            new ResourceDescriptor(
                OrderModule::class,
                'routes',
                'routes/admin.php',
                '/workspace/Order/routes/admin.php',
                attributes: [
                    'group' => [
                        'as' => 'order.admin.',
                        'namespace' => 'Workbench\\App\\Modules\\Order\\Http\\Controllers',
                    ],
                ],
            ),
            new ResourceDescriptor(
                OrderModule::class,
                'assets',
                'resources/js/order.js',
                '/workspace/Order/resources/js/order.js',
                attributes: ['type' => 'input', 'publish_to' => null],
            ),
            new ResourceDescriptor(
                OrderModule::class,
                'assets',
                'resources/public/icon.svg',
                '/workspace/Order/resources/public/icon.svg',
                attributes: ['type' => 'public', 'publish_to' => 'vendor/order/icon.svg'],
                collisionKey: 'vendor/order/icon.svg',
            ),
        ]);
        $checks = $this->gate(new ModuleRegistry([$order]), $resources)->checks(
            $order,
            new ModuleDescriptor(
                OrderModule::class,
                [],
                [PortableRuntimeServiceProvider::class],
            ),
        );

        self::assertSame([
            'MOD-EXTRACT-PLUGIN-001',
            'MOD-EXTRACT-NAMESPACE-001',
            'MOD-EXTRACT-COLLISION-001',
            'MOD-EXTRACT-PUBLISH-001',
            'MOD-EXTRACT-BINDING-001',
        ], array_map(static fn (ExtractabilityCheck $check): string => $check->code(), $checks));
        self::assertSame(
            array_fill(0, 5, ExtractabilityCheck::PASSED),
            array_map(static fn (ExtractabilityCheck $check): string => $check->status(), $checks),
        );
        self::assertStringContainsString(
            'abstract=class:Workbench\\App\\Modules\\Order\\OrderModule',
            $checks[4]->evidence()[0],
        );
    }

    public function test_unknown_plugin_unscoped_namespace_collision_unsafe_publish_and_bindings_block(): void
    {
        $order = $this->module('Order', OrderModule::class);
        $foundation = $this->module('Foundation', FoundationModule::class);
        $resources = new ResourceManifest(
            [OrderModule::class, FoundationModule::class],
            [
                new ResourceDescriptor(
                    OrderModule::class,
                    'custom',
                    'runtime',
                    attributes: ['driver' => 'application'],
                ),
                new ResourceDescriptor(
                    OrderModule::class,
                    'policies',
                    ApplicationGlobalContract::class,
                    attributes: [
                        'subject' => ApplicationGlobalContract::class,
                        'handler' => ApplicationGlobalPolicy::class,
                    ],
                    collisionKey: 'application-policy',
                ),
                new ResourceDescriptor(
                    OrderModule::class,
                    'config',
                    'order.php',
                    '/workspace/Order/config/settings.php',
                    'shared',
                    ['publish' => true],
                    'shared',
                ),
                new ResourceDescriptor(
                    FoundationModule::class,
                    'config',
                    'foundation.php',
                    '/workspace/Foundation/config/settings.php',
                    'shared',
                    ['publish' => true],
                    'shared',
                ),
                new ResourceDescriptor(
                    OrderModule::class,
                    'assets',
                    'icon.svg',
                    '/workspace/Order/icon.svg',
                    attributes: ['type' => 'public', 'publish_to' => '../escape.svg'],
                    collisionKey: '../escape.svg',
                ),
            ],
        );
        $checks = $this->gate(
            new ModuleRegistry([$order, $foundation]),
            $resources,
        )->checks(
            $order,
            new ModuleDescriptor(OrderModule::class, [], [RiskyRuntimeServiceProvider::class]),
        );

        self::assertSame(
            array_fill(0, 5, ExtractabilityCheck::BLOCKED),
            array_map(static fn (ExtractabilityCheck $check): string => $check->status(), $checks),
        );
        self::assertStringContainsString(
            'unsupported_plugin=custom:runtime',
            implode("\n", $checks[0]->evidence()),
        );
        self::assertContains('config:order.php=shared', $checks[1]->evidence());
        self::assertStringStartsWith('config=shared:', $checks[2]->evidence()[0]);
        self::assertContains('unsafe_target=public:../escape.svg', $checks[3]->evidence());
        self::assertStringContainsString('unscoped_string:shared.service', implode('\n', $checks[4]->evidence()));
        self::assertStringContainsString('application_global_class', implode('\n', $checks[4]->evidence()));
        self::assertStringContainsString('contextual_binding', implode('\n', $checks[4]->evidence()));
    }

    public function test_unrelated_manifest_collision_does_not_block_selected_module(): void
    {
        $order = $this->module('Order', OrderModule::class);
        $foundation = $this->module('Foundation', FoundationModule::class);
        $stripe = $this->module('Stripe', StripeModule::class);
        $resources = new ResourceManifest(
            [OrderModule::class, FoundationModule::class, StripeModule::class],
            [
                new ResourceDescriptor(
                    OrderModule::class,
                    'views',
                    'order',
                    '/workspace/Order/views',
                    'order',
                    collisionKey: 'order',
                ),
                new ResourceDescriptor(
                    FoundationModule::class,
                    'views',
                    'foundation',
                    '/workspace/Foundation/views',
                    'shared',
                    collisionKey: 'shared',
                ),
                new ResourceDescriptor(
                    StripeModule::class,
                    'views',
                    'stripe',
                    '/workspace/Stripe/views',
                    'shared',
                    collisionKey: 'shared',
                ),
            ],
        );
        $checks = $this->gate(
            new ModuleRegistry([$order, $foundation, $stripe]),
            $resources,
        )->checks($order, new ModuleDescriptor(OrderModule::class, [], []));

        self::assertSame(ExtractabilityCheck::PASSED, $checks[2]->status());
        self::assertSame(['collisions=none'], $checks[2]->evidence());
    }

    /**
     * @param class-string<\Cluion\Moduark\Module> $class
     */
    private function module(string $name, string $class): DiscoveredModule
    {
        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();
        self::assertIsString($file);

        return new DiscoveredModule(
            $name,
            $class,
            $file,
            $reflection->getNamespaceName(),
        );
    }

    private function gate(ModuleRegistry $registry, ResourceManifest $resources): PortableRuntimeGate
    {
        return new PortableRuntimeGate(
            $resources,
            $registry,
            ModulesConfig::from([
                'path' => dirname(__DIR__, 2).'/workbench/app/Modules',
                'activation' => ['path' => dirname(__DIR__, 2).'/workbench/moduark-modules.json'],
                'architecture' => ['level' => 1, 'rules' => []],
            ], []),
            dirname(__DIR__, 2).'/vendor',
            new ProviderBindingScanner,
        );
    }
}

final class PortableRuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderModule::class, static fn (): OrderModule => new OrderModule);
        $this->app->instance('moduark.order.runtime-ready', true);
    }
}

final class RiskyRuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $abstract = OrderModule::class;
        $container = $this->app;
        $this->app->bind('shared.service', static fn (): OrderModule => new OrderModule);
        $this->app->bind(ApplicationGlobalContract::class, ApplicationGlobalPolicy::class);
        $this->app->bind($abstract, static fn (): OrderModule => new OrderModule);
        $container->singleton('order.dynamic-receiver', static fn (): OrderModule => new OrderModule);
        $this->app->when(OrderModule::class);
    }
}

interface ApplicationGlobalContract
{
}

final class ApplicationGlobalPolicy
{
}
