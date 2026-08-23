<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cluion\Moduark\Module;
use Cluion\Moduark\Resources\ModuleAssetManifest;
use Cluion\Moduark\Resources\ResourceDescriptor;
use Cluion\Moduark\Resources\ResourceManifest;
use PHPUnit\Framework\TestCase;

final class ModuleAssetManifestTest extends TestCase
{
    public function test_vite_inputs_are_deterministic_and_exclude_public_publish_assets(): void
    {
        $assets = new ModuleAssetManifest(new ResourceManifest(
            [AssetManifestModule::class],
            [
                new ResourceDescriptor(
                    AssetManifestModule::class,
                    'assets',
                    'resources/js/zeta.js',
                    '/modules/Asset/resources/js/zeta.js',
                    attributes: ['type' => 'input', 'publish_to' => null],
                ),
                new ResourceDescriptor(
                    AssetManifestModule::class,
                    'assets',
                    'public/icon.svg',
                    '/modules/Asset/public/icon.svg',
                    attributes: ['type' => 'public', 'publish_to' => 'vendor/asset/icon.svg'],
                ),
                new ResourceDescriptor(
                    AssetManifestModule::class,
                    'assets',
                    'resources/css/alpha.css',
                    '/modules/Asset/resources/css/alpha.css',
                    attributes: ['type' => 'input', 'publish_to' => null],
                ),
            ],
        ));

        self::assertSame([
            '/modules/Asset/resources/css/alpha.css',
            '/modules/Asset/resources/js/zeta.js',
        ], $assets->inputs());
        self::assertSame([
            'schema_version' => 1,
            'modules' => [AssetManifestModule::class],
            'inputs' => $assets->inputs(),
        ], $assets->toArray());
    }
}

final class AssetManifestModule extends Module
{
}
