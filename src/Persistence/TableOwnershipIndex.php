<?php

declare(strict_types=1);

namespace Cluion\Moduark\Persistence;

use Cluion\Moduark\Exceptions\InvalidModuleMetadata;
use Cluion\Moduark\Metadata\ModuleDescriptor;
use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class TableOwnershipIndex
{
    /** @var array<string, array{table: string, owner: class-string<Module>}> */
    private array $ownership;

    /** @var array<class-string<Module>, list<string>> */
    private array $tablesByOwner;

    /**
     * @param list<ModuleDescriptor> $descriptors
     */
    public function __construct(array $descriptors)
    {
        /** @var array<string, array<class-string<Module>, string>> $claims */
        $claims = [];
        $seenOwners = [];

        foreach ($descriptors as $descriptor) {
            $owner = $descriptor->moduleClass();

            if (isset($seenOwners[$owner])) {
                throw new InvalidArgumentException(
                    "Table ownership descriptor [{$owner}] was provided more than once.",
                );
            }

            $seenOwners[$owner] = true;

            foreach ($descriptor->tables() as $table) {
                if (! TableName::valid($table)) {
                    throw InvalidModuleMetadata::invalidTableName($owner, $table);
                }

                $claims[TableName::key($table)][$owner] = $table;
            }
        }

        ksort($claims, SORT_STRING);
        $ownership = [];
        $tablesByOwner = [];

        foreach ($claims as $key => $owners) {
            ksort($owners, SORT_STRING);

            if (count($owners) > 1) {
                throw InvalidModuleMetadata::duplicateTableOwnership(
                    $key,
                    array_keys($owners),
                );
            }

            $owner = array_key_first($owners);

            if ($owner === null) {
                continue;
            }

            $table = $owners[$owner];
            $ownership[$key] = ['table' => $table, 'owner' => $owner];
            $tablesByOwner[$owner][] = $table;
        }

        foreach ($tablesByOwner as &$tables) {
            usort($tables, static function (string $left, string $right): int {
                $folded = strcasecmp($left, $right);

                return $folded !== 0 ? $folded : strcmp($left, $right);
            });
        }
        unset($tables);

        ksort($tablesByOwner, SORT_STRING);

        $this->ownership = $ownership;
        $this->tablesByOwner = $tablesByOwner;
    }

    /**
     * @return class-string<Module>|null
     */
    public function owner(string $table): ?string
    {
        return $this->ownership[TableName::key($table)]['owner'] ?? null;
    }

    /**
     * @param class-string<Module> $moduleClass
     * @return list<string>
     */
    public function tablesFor(string $moduleClass): array
    {
        return $this->tablesByOwner[$moduleClass] ?? [];
    }

    /**
     * @param class-string<Module> $moduleClass
     */
    public function owns(string $moduleClass, string $table): bool
    {
        return $this->owner($table) === $moduleClass;
    }

    /**
     * @return array<string, class-string<Module>>
     */
    public function all(): array
    {
        $ownership = [];

        foreach ($this->ownership as $entry) {
            $ownership[$entry['table']] = $entry['owner'];
        }

        return $ownership;
    }
}
