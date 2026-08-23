<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Tests\Support\LaravelMakerInventory;
use Tests\TestCase;

final class LaravelMakerInventoryTest extends TestCase
{
    public function test_native_maker_inventory_matches_the_reviewed_major_fixture(): void
    {
        $major = explode('.', Application::VERSION, 2)[0];
        $fixture = dirname(__DIR__).'/Fixtures/Generation/laravel-'.$major.'.json';

        self::assertFileExists(
            $fixture,
            "Laravel {$major} does not have a reviewed Maker inventory fixture.",
        );

        $expected = json_decode(
            (string) file_get_contents($fixture),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            $expected,
            LaravelMakerInventory::capture($this->application()),
            "Laravel {$major} Maker commands or options drifted. Review the diff and update the fixture deliberately.",
        );
    }
}
