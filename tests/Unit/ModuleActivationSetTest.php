<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Discovery\ModuleActivationSet;
use Cluion\Moduark\Discovery\NwidartModuleActivationResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ModuleActivationSetTest extends TestCase
{
    private string $statusesPath;

    protected function setUp(): void
    {
        $this->statusesPath = sys_get_temp_dir().'/moduark-statuses-'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->statusesPath)) {
            unlink($this->statusesPath);
        }
    }

    public function test_standalone_set_includes_every_module_with_a_stable_fingerprint(): void
    {
        $set = ModuleActivationSet::all();

        self::assertTrue($set->includes('User'));
        self::assertTrue($set->includes('Order'));
        self::assertSame('all:v1', $set->fingerprint());
    }

    public function test_explicit_set_is_deterministic_and_filters_inactive_modules(): void
    {
        $first = ModuleActivationSet::only(['User', 'Order', 'User']);
        $second = ModuleActivationSet::only(['Order', 'User']);

        self::assertTrue($first->includes('User'));
        self::assertFalse($first->includes('Billing'));
        self::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function test_nwidart_resolver_uses_only_enabled_module_names(): void
    {
        $root = dirname(__DIR__).'/Fixtures/Discovery/Valid/Modules';
        $activator = new class
        {
            public function hasStatus(string $name, bool $status): bool
            {
                return $name === 'Zeta' && $status;
            }
        };

        $set = (new NwidartModuleActivationResolver)->resolve($activator, $root);

        self::assertTrue($set->includes('Zeta'));
        self::assertFalse($set->includes('Alpha'));
    }

    public function test_nwidart_resolver_rejects_an_invalid_activator_contract(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must expose hasStatus()');

        (new NwidartModuleActivationResolver)->resolve(new class {}, __DIR__);
    }

    public function test_nwidart_file_resolver_matches_file_activator_status_semantics(): void
    {
        file_put_contents($this->statusesPath, json_encode([
            'Alpha' => false,
            'Zeta' => true,
        ], JSON_THROW_ON_ERROR));

        $set = (new NwidartModuleActivationResolver)->resolveFile(
            $this->statusesPath,
            dirname(__DIR__).'/Fixtures/Discovery/Valid/Modules',
        );

        self::assertFalse($set->includes('Alpha'));
        self::assertTrue($set->includes('Zeta'));
    }

    public function test_missing_nwidart_statuses_disable_every_module(): void
    {
        $set = (new NwidartModuleActivationResolver)->resolveFile(
            $this->statusesPath,
            dirname(__DIR__).'/Fixtures/Discovery/Valid/Modules',
        );

        self::assertFalse($set->includes('Alpha'));
        self::assertFalse($set->includes('Zeta'));
    }
}
