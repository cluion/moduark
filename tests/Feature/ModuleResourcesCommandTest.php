<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceInspector;
use Cluion\Moduark\Resources\ResourceManifest;
use Illuminate\Contracts\Console\Kernel;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Workbench\App\Modules\Order\OrderModule;
use Workbench\App\Modules\User\UserModule;

final class ModuleResourcesCommandTest extends TestCase
{
    public function test_resources_lists_the_complete_order_runtime_manifest(): void
    {
        [$exitCode, $payload] = $this->jsonOutput('moduark:resources', ['module' => 'Order']);
        $resources = $payload['resources'];

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertIsArray($resources);
        self::assertContains('extensions', array_column($resources, 'plugin'));
        self::assertContains('resources/js/order.js', array_column($resources, 'identity'));
    }

    public function test_resources_json_is_deterministic_and_machine_readable(): void
    {
        [$firstCode, $first, $firstJson] = $this->jsonOutput('moduark:resources', ['module' => 'Order']);
        [$secondCode, $second, $secondJson] = $this->jsonOutput('moduark:resources', ['module' => 'Order']);

        self::assertSame(ExitPolicy::SUCCESS, $firstCode);
        self::assertSame($firstCode, $secondCode);
        self::assertSame($firstJson, $secondJson);
        self::assertSame($first, $second);
        self::assertSame(1, $first['schema_version']);
        self::assertSame('passed', $first['status']);
        self::assertFalse($first['cached']);
        self::assertIsArray($first['modules']);
        self::assertCount(1, $first['modules']);
        self::assertNotEmpty($first['resources']);
        self::assertSame([], $first['collisions']);
        self::assertSame(ExitPolicy::SUCCESS, $first['exit_code']);
        self::assertNull($first['error']);
    }

    public function test_resources_reports_unknown_module_and_format_as_tool_errors(): void
    {
        $this->command('moduark:resources Missing')
            ->expectsOutputToContain('Module [Missing] is not active or does not exist.')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        $this->command('moduark:resources --format=xml')
            ->expectsOutputToContain('must be text or json')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);
    }

    public function test_resources_and_doctor_report_a_known_disabled_module_without_loading_it(): void
    {
        $this->application()->instance(
            ModuleActivationSet::class,
            ModuleActivationSet::fromStates([
                'Billing' => false,
                'Order' => true,
                'User' => true,
            ]),
        );
        $this->application()->forgetInstance(ResourceInspector::class);

        [$resourceCode, $resources] = $this->jsonOutput('moduark:resources', ['module' => 'Billing']);
        $this->application()->forgetInstance(ResourceInspector::class);
        [$doctorCode, $doctor] = $this->jsonOutput('moduark:doctor', ['module' => 'Billing']);

        self::assertSame(ExitPolicy::SUCCESS, $resourceCode);
        self::assertIsArray($resources['modules']);
        self::assertIsArray($resources['modules'][0]);
        self::assertSame('disabled', $resources['modules'][0]['state']);
        self::assertSame([], $resources['resources']);
        self::assertSame([], $resources['collisions']);
        self::assertSame(ExitPolicy::SUCCESS, $doctorCode);
        self::assertIsArray($doctor['modules']);
        self::assertIsArray($doctor['modules'][0]);
        self::assertSame('disabled', $doctor['modules'][0]['state']);
        self::assertSame([], $doctor['issues']);
    }

    public function test_doctor_reports_framework_cache_and_graph_prerequisites(): void
    {
        $this->command('moduark:doctor Order')
            ->expectsOutputToContain('Order')
            ->expectsOutputToContain('Moduark doctor found no runtime resource issues.')
            ->assertSuccessful();

        [$exitCode, $payload] = $this->jsonOutput('moduark:doctor', ['module' => 'Order']);

        self::assertSame(ExitPolicy::SUCCESS, $exitCode);
        self::assertSame('healthy', $payload['status']);
        self::assertTrue($payload['framework_supported']);
        self::assertFalse($payload['cached']);
        self::assertSame([], $payload['issues']);
        self::assertIsArray($payload['modules']);
        self::assertIsArray($payload['modules'][0]);
        self::assertSame(['Workbench\App\Modules\User\UserModule'], $payload['modules'][0]['dependencies']);
    }

    public function test_resources_and_doctor_report_deterministic_collisions(): void
    {
        $this->application()->instance(ResourceManifest::class, new ResourceManifest(
            [UserModule::class, OrderModule::class],
            [
                new ResourceDescriptor(
                    UserModule::class,
                    'config',
                    'user.php',
                    collisionKey: 'shared',
                ),
                new ResourceDescriptor(
                    OrderModule::class,
                    'config',
                    'order.php',
                    collisionKey: 'shared',
                ),
            ],
        ));
        $this->application()->forgetInstance(ResourceInspector::class);

        [$resourceCode, $resources] = $this->jsonOutput('moduark:resources');
        $this->application()->forgetInstance(ResourceInspector::class);
        [$doctorCode, $doctor] = $this->jsonOutput('moduark:doctor');

        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $resourceCode);
        self::assertSame('collisions_found', $resources['status']);
        self::assertIsArray($resources['collisions']);
        self::assertCount(1, $resources['collisions']);
        self::assertSame(ExitPolicy::VIOLATIONS_FOUND, $doctorCode);
        self::assertIsArray($doctor['issues']);
        self::assertIsArray($doctor['issues'][0]);
        self::assertSame('resource_collision', $doctor['issues'][0]['code']);
    }

    /**
     * @param array<string, string> $parameters
     * @return array{int, array<mixed>, string}
     * @throws JsonException
     */
    private function jsonOutput(string $command, array $parameters = []): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            $command,
            ['--format' => 'json', ...$parameters],
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload, $json];
    }
}
