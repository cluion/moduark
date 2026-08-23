<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cluion\Moduark\Architecture\ExitPolicy;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Generation\GenerationFileTemplate;
use Cluion\Moduark\Generation\GenerationOptions;
use Cluion\Moduark\Generation\GenerationPlan;
use Cluion\Moduark\Generation\GenerationTarget;
use Cluion\Moduark\Generation\GeneratorDescriptor;
use Cluion\Moduark\Generation\GeneratorRegistration;
use Cluion\Moduark\Generation\ModuleMakerTarget;
use Cluion\Moduark\Generation\ModuleMakerTargetResolver;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class ThirdPartyGeneratorTest extends TestCase
{
    private string $temporaryBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryBasePath = sys_get_temp_dir().'/moduark-extension-'.bin2hex(random_bytes(8));
        $modulePath = $this->temporaryBasePath.'/app/Modules/User';

        self::assertTrue(mkdir($modulePath, 0755, true));
        self::assertIsInt(file_put_contents(
            $this->temporaryBasePath.'/composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'ExtensionFixture\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        ));
        self::assertIsInt(file_put_contents($modulePath.'/UserModule.php', "<?php\n"));

        $this->application()->setBasePath($this->temporaryBasePath);
        $this->application()->instance(
            ModuleMakerTargetResolver::class,
            new ModuleMakerTargetResolver(
                $this->application(),
                new ModuleRegistry([
                    new DiscoveredModule(
                        'User',
                        Module::class,
                        $modulePath.'/UserModule.php',
                        'ExtensionFixture\\Modules\\User',
                    ),
                ]),
            ),
        );
        GeneratorRegistration::register(
            $this->application(),
            new ExtensionFixtureDescriptor(
                dirname(__DIR__).'/Fixtures/Generation/extension-generator.stub',
                dirname(__DIR__).'/Fixtures/Generation/extension-generator-broken.stub',
            ),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryBasePath);

        parent::tearDown();
    }

    public function test_custom_generator_uses_shared_json_preflight_and_force_execution(): void
    {
        $path = $this->temporaryBasePath.'/app/Modules/User/ValueObjects/Money.php';

        [$exitCode, $planned, $json] = $this->jsonPlan('Money');

        self::assertSame(0, $exitCode);
        self::assertSame('planned', $planned['status']);
        self::assertSame('value-object', $planned['generator_id']);
        self::assertIsArray($planned['targets'] ?? null);
        self::assertIsArray($planned['targets'][0] ?? null);
        $plannedTarget = $planned['targets'][0];
        self::assertSame('ValueObjects/Money.php', $plannedTarget['path']);
        self::assertSame('value-object', $plannedTarget['generator_id']);
        self::assertFalse($plannedTarget['overwrite']);
        self::assertStringNotContainsString($this->temporaryBasePath, $json);
        self::assertStringNotContainsString('extension-generator.stub', $json);
        self::assertFileDoesNotExist($path);

        $this->command('moduark:make User value-object Money')->assertSuccessful();

        self::assertFileExists($path);
        self::assertStringContainsString(
            'namespace ExtensionFixture\\Modules\\User\\ValueObjects;',
            (string) file_get_contents($path),
        );

        [$collisionExit, $collision] = $this->jsonPlan('Money');

        self::assertSame(1, $collisionExit);
        self::assertSame('collisions_found', $collision['status']);
        self::assertIsArray($collision['targets'] ?? null);
        self::assertIsArray($collision['targets'][0] ?? null);
        self::assertTrue($collision['targets'][0]['collision']);

        self::assertIsInt(file_put_contents($path, "existing\n"));
        [$forceExit, $force] = $this->jsonPlan('Money', true);

        self::assertSame(0, $forceExit);
        self::assertIsArray($force['targets'] ?? null);
        self::assertIsArray($force['targets'][0] ?? null);
        self::assertSame('overwrite', $force['targets'][0]['operation']);
        self::assertTrue($force['targets'][0]['overwrite']);

        $this->command('moduark:make User value-object Money --force')->assertSuccessful();
        self::assertStringNotContainsString('existing', (string) file_get_contents($path));
    }

    public function test_custom_composite_template_failure_rolls_back_every_target(): void
    {
        $first = $this->temporaryBasePath.'/app/Modules/User/ValueObjects/Broken.php';
        $second = $this->temporaryBasePath.'/app/Modules/User/ValueObjects/BrokenMetadata.php';

        $this->command('moduark:make User value-object Broken')
            ->expectsOutputToContain('has unresolved placeholders')
            ->expectsOutputToContain('all planned filesystem changes were rolled back')
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist($first);
        self::assertFileDoesNotExist($second);
    }

    public function test_custom_generator_options_are_centrally_allowlisted(): void
    {
        $this->command('moduark:make User value-object Money --invokable')
            ->expectsOutputToContain(
                'The --invokable option is not supported for Maker type [value-object].',
            )
            ->assertExitCode(ExitPolicy::TOOL_ERROR);

        self::assertFileDoesNotExist(
            $this->temporaryBasePath.'/app/Modules/User/ValueObjects/Money.php',
        );
    }

    /** @return array{int, array<mixed>, string} */
    private function jsonPlan(string $name, bool $force = false): array
    {
        $output = new BufferedOutput;
        $exitCode = $this->application()->make(Kernel::class)->call(
            'moduark:make',
            [
                'module' => 'User',
                'type' => 'value-object',
                'name' => $name,
                '--dry-run' => true,
                '--format' => 'json',
                '--force' => $force,
            ],
            $output,
        );
        $json = trim($output->fetch());
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return [$exitCode, $payload, $json];
    }
}

final readonly class ExtensionFixtureDescriptor implements GeneratorDescriptor
{
    public function __construct(
        private string $stub,
        private string $brokenStub,
    ) {
    }

    public function id(): string
    {
        return 'value-object';
    }

    public function targetNamespace(): string
    {
        return 'ValueObjects';
    }

    public function supportedOptions(): array
    {
        return ['force'];
    }

    public function plan(
        ModuleMakerTarget $target,
        GenerationOptions $options,
    ): GenerationPlan {
        $targets = [$this->target($target, $options, $this->stub)];

        if ($target->localName() === 'Broken') {
            $targets[] = new GenerationTarget(
                $this->id(),
                null,
                $target->moduleNamespace().'\\ValueObjects\\BrokenMetadata',
                $target->modulePath().'/ValueObjects/BrokenMetadata.php',
                'ValueObjects/BrokenMetadata.php',
                $options->force,
                [],
                new GenerationFileTemplate($this->brokenStub, [
                    '{{ namespace }}' => $target->moduleNamespace().'\\ValueObjects',
                ]),
            );
        }

        return new GenerationPlan($targets);
    }

    private function target(
        ModuleMakerTarget $target,
        GenerationOptions $options,
        string $stub,
    ): GenerationTarget {
        $segments = explode('\\', $target->className());
        $class = array_pop($segments);

        return new GenerationTarget(
            $this->id(),
            null,
            $target->className(),
            $target->filePath(),
            $target->moduleRelativePath(),
            $options->force,
            [],
            new GenerationFileTemplate($stub, [
                '{{ namespace }}' => implode('\\', $segments),
                '{{ class }}' => $class,
            ]),
        );
    }
}
