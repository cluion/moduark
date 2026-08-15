<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Analysis\AnalysisContext;
use Cluion\Moduark\Analysis\Boundary\ConventionPublicApi;
use Cluion\Moduark\Analysis\Rules\InternalApiAccessRule;
use Cluion\Moduark\Analysis\Source\SourceIndexBuilder;
use Cluion\Moduark\Architecture\RuleId;
use Cluion\Moduark\Discovery\DiscoveredModule;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use Cluion\Moduark\Registry\ModuleRegistry;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Analysis\Modules\Order\OrderModule;
use Tests\Fixtures\Analysis\Modules\User\UserModule;

final class InternalApiAccessRuleTest extends TestCase
{
    public function test_cross_module_internal_references_are_reported_with_source_evidence(): void
    {
        $result = (new InternalApiAccessRule(new ConventionPublicApi))->inspect(
            $this->context(),
            RuleId::InternalApiAccess->defaultSeverity(),
        );

        self::assertFalse($result->passed());
        self::assertSame([
            'rule' => 'internal_api_access',
            'code' => 'MOD-BOUNDARY-001',
            'severity' => 'error',
            'message' => 'Module [Order] accesses an internal symbol from Module [User].',
            'file' => dirname(__DIR__).'/Fixtures/Analysis/Modules/Order/Actions/ObservedReferences.php',
            'line' => 15,
            'consumer' => 'Order',
            'target' => 'User',
            'symbol' => 'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'suggestion' => 'Use User/Contracts, User/Data, or User/Events instead.',
        ], $result->violations()[0]->toArray());

        $symbols = array_values(array_unique(array_map(
            static fn ($violation): ?string => $violation->symbol(),
            $result->violations(),
        )));
        sort($symbols, SORT_STRING);

        self::assertSame([
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Attributes\\UserMarker',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Base\\UserBase',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Exceptions\\UserFailure',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Services\\UserService',
            'Tests\\Fixtures\\Analysis\\Modules\\User\\Support\\UserTrait',
        ], $symbols);
    }

    private function context(): AnalysisContext
    {
        $root = dirname(__DIR__).'/Fixtures/Analysis/Modules';
        $registry = new ModuleRegistry([
            $this->module('User', UserModule::class, $root),
            $this->module('Order', OrderModule::class, $root),
        ]);

        return new AnalysisContext($registry, [
            new ModuleDescriptor(OrderModule::class, [UserModule::class], []),
            new ModuleDescriptor(UserModule::class, [], []),
        ], (new SourceIndexBuilder($registry))->build());
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    private function module(string $name, string $moduleClass, string $root): DiscoveredModule
    {
        return new DiscoveredModule(
            $name,
            $moduleClass,
            "{$root}/{$name}/{$name}Module.php",
            "Tests\\Fixtures\\Analysis\\Modules\\{$name}",
        );
    }
}
