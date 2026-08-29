<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Extraction\ArchitectureExtractabilityGate;
use Cluion\Moduark\Extraction\ExtractabilityCheck;
use Cluion\Moduark\Extraction\ExtractabilityInspector;
use Cluion\Moduark\Extraction\PortableRuntimeGate;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Registry\ModuleRegistry;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use ReflectionClass;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Nwidart\Modules\User\UserModule as NwidartUserModule;
use Tests\Fixtures\ProbeController;
use Tests\Fixtures\RouteLoadingServiceProvider;
use Tests\TestCase;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\User\UserModule;

final class ModuleExtractabilityCommandTest extends TestCase
{
    /** @throws JsonException */
    public function test_extractability_report_is_deterministic_and_ready_only_for_export_dry_run(): void
    {
        [$firstCode, $first, $firstJson] = $this->jsonOutput('Order');
        [$secondCode, $second, $secondJson] = $this->jsonOutput('Order');

        self::assertSame(ExitPolicy::SUCCESS, $firstCode);
        self::assertSame($firstCode, $secondCode);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame([
            'schema_version',
            'mode',
            'status',
            'module',
            'checks',
            'blockers',
            'exit_code',
            'error',
        ], array_keys($first));
        self::assertSame(1, $first['schema_version']);
        self::assertSame('extractability', $first['mode']);
        self::assertSame('ready_for_export_dry_run', $first['status']);
        self::assertIsArray($first['module']);
        self::assertSame('Order', $first['module']['name']);
        self::assertIsArray($first['checks']);
        self::assertSame([
            'MOD-EXTRACT-LAYOUT-001',
            'MOD-EXTRACT-AUTOLOAD-001',
            'MOD-EXTRACT-PROVIDER-001',
            'MOD-EXTRACT-RESOURCE-001',
            'MOD-EXTRACT-COUPLING-001',
            'MOD-EXTRACT-DEPENDENCY-001',
            'MOD-EXTRACT-CAPABILITY-001',
            'MOD-EXTRACT-TABLE-001',
            'MOD-EXTRACT-FK-001',
            'MOD-EXTRACT-TRANSACTION-001',
            'MOD-EXTRACT-EXPORT-001',
            'MOD-EXTRACT-PLUGIN-001',
            'MOD-EXTRACT-NAMESPACE-001',
            'MOD-EXTRACT-COLLISION-001',
            'MOD-EXTRACT-PUBLISH-001',
            'MOD-EXTRACT-BINDING-001',
        ], array_column($first['checks'], 'code'));
        self::assertSame([], $first['blockers']);
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertNull($first['error']);
    }

    /** @throws JsonException */
    public function test_application_owned_provider_resource_and_metadata_are_blockers(): void
    {
        $this->application()->instance(ModuleMetadataCompiler::class, new ModuleMetadataCompiler([
            new ModuleDescriptor(
                OrderModule::class,
                [UserModule::class],
                [RouteLoadingServiceProvider::class],
                exports: [ProbeController::class],
            ),
        ]));
        $this->application()->instance(ResourceManifest::class, new ResourceManifest(
            [OrderModule::class],
            [
                new ResourceDescriptor(
                    OrderModule::class,
                    'routes',
                    'outside.php',
                    __DIR__.'/../Fixtures/routes.php',
                ),
            ],
        ));
        $this->application()->forgetInstance(ExtractabilityInspector::class);

        [$exitCode, $payload] = $this->jsonOutput('Order');

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $exitCode);
        self::assertSame('blocked', $payload['status']);
        self::assertIsArray($payload['blockers']);
        self::assertSame([
            'MOD-EXTRACT-PROVIDER-001',
            'MOD-EXTRACT-RESOURCE-001',
            'MOD-EXTRACT-COUPLING-001',
        ], array_slice(array_column($payload['blockers'], 'code'), 0, 3));
        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $payload['exit_code']);
    }

    /** @throws JsonException */
    public function test_extractability_requires_one_active_module(): void
    {
        [$missingCode, $missing] = $this->jsonOutput(null);
        [$unknownCode, $unknown] = $this->jsonOutput('Missing');

        self::assertSame(ExitPolicy::TOOL_ERROR, $missingCode);
        self::assertSame('error', $missing['status']);
        self::assertSame('The --extractable option requires one active Module name.', $missing['error']);
        self::assertSame(ExitPolicy::TOOL_ERROR, $unknownCode);
        self::assertSame('error', $unknown['status']);
        self::assertSame('Module [Missing] is not active or does not exist.', $unknown['error']);
    }

    public function test_nwidart_resources_are_owned_by_the_full_module_root(): void
    {
        $fixtureRoot = dirname(__DIR__).'/Fixtures/Nwidart/Modules/User';
        $loader = new ClassLoader;
        $loader->addPsr4('Tests\\Fixtures\\Nwidart\\Modules\\User\\', $fixtureRoot.'/app');
        $loader->register(true);

        try {
            $moduleFile = (new ReflectionClass(NwidartUserModule::class))->getFileName();
            self::assertIsString($moduleFile);
            $modulePath = dirname($moduleFile);
            $moduleRoot = dirname($modulePath);
            $entry = $modulePath.DIRECTORY_SEPARATOR.'UserModule.php';
            $resource = $moduleRoot.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php';
            $configured = $this->application()->make(ModulesConfig::class)->all();
            $configuration = ModulesConfig::from($configured, [
                'path' => dirname($moduleRoot),
            ]);
            $module = new DiscoveredModule(
                'User',
                NwidartUserModule::class,
                $entry,
                'Tests\\Fixtures\\Nwidart\\Modules\\User',
            );
            $inspector = new ExtractabilityInspector(
                new ModuleRegistry([$module]),
                new ModuleMetadataCompiler,
                new ResourceManifest(
                    [NwidartUserModule::class],
                    [new ResourceDescriptor(NwidartUserModule::class, 'routes', 'web.php', $resource)],
                ),
                $configuration,
                base_path('vendor'),
                $this->application()->make(ArchitectureExtractabilityGate::class),
                $this->application()->make(PortableRuntimeGate::class),
            );

            $report = $inspector->inspect('User')->toArray();

            self::assertSame('ready_for_export_dry_run', $report['status']);
            self::assertSame([], $report['blockers']);
            self::assertSame(
                ExtractabilityCheck::PASSED,
                $report['checks'][3]['status'],
            );
            self::assertSame(['routes:web.php='.$resource], $report['checks'][3]['evidence']);
        } finally {
            $loader->unregister();
        }
    }

    /**
     * @return array{int, array<string, mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(?string $module): array
    {
        $output = new BufferedOutput;
        $arguments = [
            '--extractable' => true,
            '--format' => 'json',
        ];

        if ($module !== null) {
            $arguments['module'] = $module;
        }

        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:doctor',
            $arguments,
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $normalized = [];

        foreach ($payload as $key => $value) {
            self::assertIsString($key);
            $normalized[$key] = $value;
        }

        return [$exitCode, $normalized, $json];
    }
}
