<?php

declare(strict_types=1);

namespace Cluion\Moduark\Lifecycle\Activation;

use Cluion\Moduark\Analysis\Source\SourceAnalysisCacheStore;
use Cluion\Moduark\Cache\ModuleCacheStore;
use Illuminate\Foundation\Application;
use RuntimeException;

final readonly class ApplicationModuleActivationCacheInvalidator implements ModuleActivationCacheInvalidator
{
    public function __construct(
        private ModuleCacheStore $moduleCache,
        private SourceAnalysisCacheStore $sourceCache,
        private Application $application,
    ) {
    }

    public function invalidate(): void
    {
        $this->sourceCache->clear();
        $this->clearFile($this->application->getCachedRoutesPath(), 'route');
        $this->clearFile($this->application->getCachedEventsPath(), 'event');
        $this->moduleCache->clear();
    }

    private function clearFile(string $path, string $cache): void
    {
        if (! is_file($path)) {
            return;
        }

        if (! @unlink($path)) {
            throw new RuntimeException("Unable to clear Laravel {$cache} cache [{$path}].");
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }
}
