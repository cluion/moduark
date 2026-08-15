<?php

declare(strict_types=1);

namespace Cluion\Moduark\Architecture;

enum RuleId: string
{
    case ValidModuleStructure = 'valid_module_structure';
    case UniqueModuleIdentity = 'unique_module_identity';
    case MissingDependencies = 'missing_dependencies';
    case UndeclaredDependencies = 'undeclared_dependencies';
    case Cycles = 'cycles';
    case InternalApiAccess = 'internal_api_access';
    case CapabilityContracts = 'capability_contracts';
    case AdapterBoundaries = 'adapter_boundaries';
    case CrossModuleModelAccess = 'cross_module_model_access';
    case DatabaseOwnership = 'database_ownership';
    case MigrationOwnership = 'migration_ownership';
    case CrossModuleForeignKeys = 'cross_module_foreign_keys';
    case CrossModuleTransactions = 'cross_module_transactions';
    case ExplicitPublicExports = 'explicit_public_exports';

    public function defaultSeverity(): Severity
    {
        return match ($this) {
            self::CrossModuleForeignKeys,
            self::CrossModuleTransactions => Severity::Warning,
            default => Severity::Error,
        };
    }
}
