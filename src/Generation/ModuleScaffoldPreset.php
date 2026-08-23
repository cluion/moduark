<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

use Cluion\Moduark\Exceptions\ModuleGenerationFailed;

enum ModuleScaffoldPreset: string
{
    case Minimal = 'minimal';
    case Web = 'web';
    case Api = 'api';
    case Domain = 'domain';
    case Full = 'full';

    public static function parse(string $value): self
    {
        $preset = self::tryFrom(strtolower(trim($value)));

        return $preset ?? throw ModuleGenerationFailed::unsupportedPreset($value);
    }

    /** @return list<ModuleScaffoldTargetDescriptor> */
    public function descriptors(): array
    {
        return match ($this) {
            self::Minimal => [ModuleScaffoldTargetDescriptor::ModuleEntry],
            self::Web => [
                ModuleScaffoldTargetDescriptor::ModuleEntry,
                ...$this->webDescriptors(),
            ],
            self::Api => [
                ModuleScaffoldTargetDescriptor::ModuleEntry,
                ...$this->apiDescriptors(),
            ],
            self::Domain => [
                ModuleScaffoldTargetDescriptor::ModuleEntry,
                ...$this->domainDescriptors(),
            ],
            self::Full => [
                ModuleScaffoldTargetDescriptor::ModuleEntry,
                ...$this->webDescriptors(),
                ...$this->apiDescriptors(),
                ...$this->domainDescriptors(),
            ],
        };
    }

    /** @return list<ModuleScaffoldTargetDescriptor> */
    private function webDescriptors(): array
    {
        return [
            ModuleScaffoldTargetDescriptor::WebRoute,
            ModuleScaffoldTargetDescriptor::WebController,
            ModuleScaffoldTargetDescriptor::WebView,
            ModuleScaffoldTargetDescriptor::EnglishTranslations,
            ModuleScaffoldTargetDescriptor::WebTest,
        ];
    }

    /** @return list<ModuleScaffoldTargetDescriptor> */
    private function apiDescriptors(): array
    {
        return [
            ModuleScaffoldTargetDescriptor::ApiRoute,
            ModuleScaffoldTargetDescriptor::ApiController,
            ModuleScaffoldTargetDescriptor::ApiRequest,
            ModuleScaffoldTargetDescriptor::ApiResource,
            ModuleScaffoldTargetDescriptor::ApiTest,
        ];
    }

    /** @return list<ModuleScaffoldTargetDescriptor> */
    private function domainDescriptors(): array
    {
        return [
            ModuleScaffoldTargetDescriptor::DomainDirectory,
            ModuleScaffoldTargetDescriptor::ApplicationDirectory,
            ModuleScaffoldTargetDescriptor::InfrastructureDirectory,
        ];
    }
}
