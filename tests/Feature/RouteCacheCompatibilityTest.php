<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\RouteLoadingServiceProvider;
use Tests\TestCase;

final class RouteCacheCompatibilityTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RouteLoadingServiceProvider::class];
    }

    public function test_service_provider_loads_routes_when_routes_are_not_cached(): void
    {
        $this->get('/__moduark-probe')->assertOk()->assertSeeText('moduark-probe');
    }

    public function test_routes_survive_testbench_route_cache_bootstrap(): void
    {
        $this->defineCacheRoutes(<<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ProbeController;

Route::get('/__moduark-cached-probe', ProbeController::class)
    ->name('moduark.cached-probe');
PHP);

        $this->get('/__moduark-cached-probe')
            ->assertOk()
            ->assertSeeText('moduark-probe');
    }
}
