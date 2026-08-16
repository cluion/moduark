<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\ArchitectureChecker;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\RuleRunner;
use Cluion\Moduark\Analysis\Rules\AdapterBoundariesRule;
use Cluion\Moduark\Analysis\Rules\CapabilityContractsRule;
use Cluion\Moduark\Analysis\Rules\CrossModuleModelAccessRule;
use Cluion\Moduark\Analysis\Rules\CyclesRule;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Rules\MissingDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UndeclaredDependenciesRule;
use Cluion\Moduark\Analysis\Rules\UniqueModuleIdentityRule;
use Cluion\Moduark\Analysis\Rules\ValidModuleStructureRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\Level;
use Cluion\Moduark\Architecture\RulePresets;
use Cluion\Moduark\Architecture\RuleResolver;
use Cluion\Moduark\Configuration\ModulesConfig;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Exceptions\SourceAnalysisFailed;
use Cluion\Moduark\Metadata\ModuleMetadataCompiler;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class ArchitectureCheckerTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryPath = sys_get_temp_dir().'/moduark-checker-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->temporaryPath, 0755, true));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->temporaryPath);

        parent::tearDown();
    }

    public function test_level_zero_does_not_parse_module_source(): void
    {
        $path = $this->temporaryPath.'/OrganizationModule.php';
        self::assertNotFalse(file_put_contents($path, "<?php\nfinal class InvalidSource {"));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Organization',
                OrganizationModule::class,
                $path,
                __NAMESPACE__,
            ),
        ]);
        $configuration = ModulesConfig::from([
            'path' => $this->temporaryPath,
            'architecture' => [
                'level' => 1,
                'rules' => [
                    'capability_contracts' => true,
                ],
            ],
        ], []);
        $checker = new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $configuration,
            new RuleResolver(new RulePresets),
            $this->runner(),
        );

        $report = $checker->check(Level::Organization);

        self::assertTrue($report->complete());
        self::assertCount(3, $report->results());
    }

    public function test_level_one_runs_source_analysis_and_reports_undeclared_dependencies(): void
    {
        $registry = $this->fixtureRegistry();
        $checker = new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $this->fixtureConfiguration(['internal_api_access' => false]),
            new RuleResolver(new RulePresets),
            $this->runner(),
        );

        $report = $checker->check();

        self::assertTrue($report->complete());
        self::assertNotEmpty($report->errors());
        self::assertSame('MOD-DEPENDENCY-002', $report->errors()[0]->code());
    }

    public function test_adapter_rule_alone_still_builds_the_source_index(): void
    {
        $path = $this->temporaryPath.'/OrganizationModule.php';
        self::assertNotFalse(file_put_contents($path, "<?php\nfinal class InvalidSource {"));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Organization',
                OrganizationModule::class,
                $path,
                __NAMESPACE__,
            ),
        ]);
        $configuration = ModulesConfig::from([
            'path' => $this->temporaryPath,
            'architecture' => [
                'level' => 0,
                'rules' => [
                    'adapter_boundaries' => true,
                ],
            ],
        ], []);
        $checker = new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $configuration,
            new RuleResolver(new RulePresets),
            $this->runner(),
        );

        $this->expectException(SourceAnalysisFailed::class);

        $checker->check(Level::Organization);
    }

    public function test_internal_rule_alone_still_builds_the_source_index(): void
    {
        $registry = $this->fixtureRegistry();
        $checker = new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $this->fixtureConfiguration(['undeclared_dependencies' => false]),
            new RuleResolver(new RulePresets),
            $this->runner(),
        );

        $report = $checker->check();

        self::assertTrue($report->complete());
        self::assertNotEmpty($report->errors());
        self::assertSame('MOD-BOUNDARY-001', $report->errors()[0]->code());
    }

    public function test_cross_module_model_rule_alone_still_builds_the_source_index(): void
    {
        $path = $this->temporaryPath.'/OrganizationModule.php';
        self::assertNotFalse(file_put_contents($path, "<?php\nfinal class InvalidSource {"));
        $registry = new ModuleRegistry([
            new DiscoveredModule(
                'Organization',
                OrganizationModule::class,
                $path,
                __NAMESPACE__,
            ),
        ]);
        $configuration = ModulesConfig::from([
            'path' => $this->temporaryPath,
            'architecture' => [
                'level' => 0,
                'rules' => [
                    'cross_module_model_access' => true,
                ],
            ],
        ], []);
        $checker = new ArchitectureChecker(
            $registry,
            new ModuleMetadataCompiler,
            new SourceIndexBuilder($registry),
            $configuration,
            new RuleResolver(new RulePresets),
            $this->runner(),
        );

        $this->expectException(SourceAnalysisFailed::class);

        $checker->check(Level::Organization);
    }

    private function runner(): RuleRunner
    {
        return new RuleRunner([
            new ValidModuleStructureRule,
            new UniqueModuleIdentityRule,
            new MissingDependenciesRule,
            new UndeclaredDependenciesRule,
            new CyclesRule,
            new InternalApiAccessRule(new ConventionPublicApi),
            new CapabilityContractsRule,
            new AdapterBoundariesRule,
            new CrossModuleModelAccessRule,
        ]);
    }

    private function fixtureRegistry(): ModuleRegistry
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules';

        return new ModuleRegistry([
            new DiscoveredModule(
                'User',
                UserModule::class,
                $root.'/User/UserModule.php',
                'Tests\\Fixtures\\Analysis\\Modules\\User',
            ),
            new DiscoveredModule(
                'Order',
                OrderModule::class,
                $root.'/Order/OrderModule.php',
                'Tests\\Fixtures\\Analysis\\Modules\\Order',
            ),
        ]);
    }

    /**
     * @param array<string, bool> $rules
     */
    private function fixtureConfiguration(array $rules): ModulesConfig
    {
        return ModulesConfig::from([
            'path' => dirname(__DIR__).'/Fixtures/Analysis/Modules',
            'architecture' => [
                'level' => 1,
                'rules' => [],
            ],
        ], [
            'architecture' => [
                'rules' => $rules,
            ],
        ]);
    }
}

final class OrganizationModule extends Module
{
}
