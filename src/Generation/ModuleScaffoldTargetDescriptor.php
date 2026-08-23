<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

enum ModuleScaffoldTargetDescriptor: string
{
    case ModuleEntry = 'module';
    case WebRoute = 'web-route';
    case WebController = 'web-controller';
    case WebView = 'web-view';
    case EnglishTranslations = 'english-translations';
    case WebTest = 'web-test';
    case ApiRoute = 'api-route';
    case ApiController = 'api-controller';
    case ApiRequest = 'api-request';
    case ApiResource = 'api-resource';
    case ApiTest = 'api-test';
    case DomainDirectory = 'domain-directory';
    case ApplicationDirectory = 'application-directory';
    case InfrastructureDirectory = 'infrastructure-directory';

    public function relativePath(ModuleName $module): string
    {
        return match ($this) {
            self::ModuleEntry => $module->entryClass().'.php',
            self::WebRoute => 'routes/web.php',
            self::WebController => 'Http/Controllers/Web/'.$module->value().'Controller.php',
            self::WebView => 'resources/views/index.blade.php',
            self::EnglishTranslations => 'resources/lang/en/messages.php',
            self::WebTest => 'Tests/Feature/Web/'.$module->value().'WebTest.php',
            self::ApiRoute => 'routes/api.php',
            self::ApiController => 'Http/Controllers/Api/'.$module->value().'Controller.php',
            self::ApiRequest => 'Http/Requests/Api/'.$module->value().'Request.php',
            self::ApiResource => 'Http/Resources/Api/'.$module->value().'Resource.php',
            self::ApiTest => 'Tests/Feature/Api/'.$module->value().'ApiTest.php',
            self::DomainDirectory => 'Domain/.gitkeep',
            self::ApplicationDirectory => 'Application/.gitkeep',
            self::InfrastructureDirectory => 'Infrastructure/.gitkeep',
        };
    }

    public function stub(): string
    {
        return match ($this) {
            self::ModuleEntry => 'module.stub',
            self::WebRoute => 'module-preset-web-route.stub',
            self::WebController => 'module-preset-web-controller.stub',
            self::WebView => 'module-preset-view.stub',
            self::EnglishTranslations => 'module-preset-translations.stub',
            self::WebTest => 'module-preset-web-test.stub',
            self::ApiRoute => 'module-preset-api-route.stub',
            self::ApiController => 'module-preset-api-controller.stub',
            self::ApiRequest => 'module-preset-api-request.stub',
            self::ApiResource => 'module-preset-api-resource.stub',
            self::ApiTest => 'module-preset-api-test.stub',
            self::DomainDirectory,
            self::ApplicationDirectory,
            self::InfrastructureDirectory => 'module-preset-empty.stub',
        };
    }

    public function identity(string $moduleNamespace, ModuleName $module): string
    {
        return match ($this) {
            self::ModuleEntry => $moduleNamespace.'\\'.$module->entryClass(),
            self::WebController => $moduleNamespace.'\\Http\\Controllers\\Web\\'
                .$module->value().'Controller',
            self::WebTest => $moduleNamespace.'\\Tests\\Feature\\Web\\'
                .$module->value().'WebTest',
            self::ApiController => $moduleNamespace.'\\Http\\Controllers\\Api\\'
                .$module->value().'Controller',
            self::ApiRequest => $moduleNamespace.'\\Http\\Requests\\Api\\'
                .$module->value().'Request',
            self::ApiResource => $moduleNamespace.'\\Http\\Resources\\Api\\'
                .$module->value().'Resource',
            self::ApiTest => $moduleNamespace.'\\Tests\\Feature\\Api\\'
                .$module->value().'ApiTest',
            default => $moduleNamespace.'::'.$this->relativePath($module),
        };
    }

    /** @return array<string, string> */
    public function replacements(string $moduleNamespace, ModuleName $module): array
    {
        $name = $module->value();
        $slug = strtolower($name);

        return [
            '{{ namespace }}' => $this->namespace($moduleNamespace),
            '{{ class }}' => $this->shortClass($module),
            '{{ module }}' => $name,
            '{{ module_slug }}' => $slug,
            '{{ view }}' => $slug.'::index',
            '{{ web_controller }}' => $moduleNamespace.'\\Http\\Controllers\\Web\\'
                .$name.'Controller',
            '{{ api_controller }}' => $moduleNamespace.'\\Http\\Controllers\\Api\\'
                .$name.'Controller',
            '{{ api_request }}' => $moduleNamespace.'\\Http\\Requests\\Api\\'
                .$name.'Request',
            '{{ api_resource }}' => $moduleNamespace.'\\Http\\Resources\\Api\\'
                .$name.'Resource',
        ];
    }

    private function namespace(string $moduleNamespace): string
    {
        return match ($this) {
            self::ModuleEntry => $moduleNamespace,
            self::WebController => $moduleNamespace.'\\Http\\Controllers\\Web',
            self::WebTest => $moduleNamespace.'\\Tests\\Feature\\Web',
            self::ApiController => $moduleNamespace.'\\Http\\Controllers\\Api',
            self::ApiRequest => $moduleNamespace.'\\Http\\Requests\\Api',
            self::ApiResource => $moduleNamespace.'\\Http\\Resources\\Api',
            self::ApiTest => $moduleNamespace.'\\Tests\\Feature\\Api',
            default => $moduleNamespace,
        };
    }

    private function shortClass(ModuleName $module): string
    {
        return match ($this) {
            self::ModuleEntry => $module->entryClass(),
            self::WebController,
            self::ApiController => $module->value().'Controller',
            self::WebTest => $module->value().'WebTest',
            self::ApiRequest => $module->value().'Request',
            self::ApiResource => $module->value().'Resource',
            self::ApiTest => $module->value().'ApiTest',
            default => $module->value(),
        };
    }
}
