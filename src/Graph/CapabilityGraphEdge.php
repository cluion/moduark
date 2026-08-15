<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Capability;
use Cluion\Moduark\Module;
use InvalidArgumentException;

final readonly class CapabilityGraphEdge
{
    /** @var class-string<Module> */
    private string $module;

    /** @var class-string<Capability> */
    private string $capability;

    /** @var null|class-string */
    private ?string $port;

    /** @var null|class-string */
    private ?string $adapter;

    /**
     * @param string $module
     * @param string $capability
     * @param null|string $port
     * @param null|string $adapter
     */
    public function __construct(
        private CapabilityGraphEdgeType $type,
        string $module,
        string $capability,
        private string $evidence,
        ?string $port = null,
        ?string $adapter = null,
    ) {
        if (! is_a($module, Module::class, true)) {
            throw new InvalidArgumentException('A Capability graph Module endpoint must extend Module.');
        }

        if ($capability === Capability::class || ! is_a($capability, Capability::class, true)) {
            throw new InvalidArgumentException(
                'A Capability graph Capability endpoint must extend Capability.',
            );
        }

        if (trim($evidence) === '') {
            throw new InvalidArgumentException('A Capability graph edge must preserve its evidence.');
        }

        if ($type === CapabilityGraphEdgeType::Requires) {
            if ($port === null || ! interface_exists($port)) {
                throw new InvalidArgumentException(
                    'A Capability requires edge must preserve its consumer Port.',
                );
            }

            if ($adapter === null || ! class_exists($adapter) || ! is_a($adapter, $port, true)) {
                throw new InvalidArgumentException(
                    'A Capability requires edge must preserve its consumer Adapter.',
                );
            }
        } elseif ($port !== null || $adapter !== null) {
            throw new InvalidArgumentException(
                'A Capability provides edge cannot declare a consumer Port or Adapter.',
            );
        }

        $this->module = $module;
        $this->capability = $capability;
        $this->port = $port;
        $this->adapter = $adapter;
    }

    public function type(): CapabilityGraphEdgeType
    {
        return $this->type;
    }

    /** @return class-string<Module> */
    public function module(): string
    {
        return $this->module;
    }

    /** @return class-string<Capability> */
    public function capability(): string
    {
        return $this->capability;
    }

    public function evidence(): string
    {
        return $this->evidence;
    }

    /** @return null|class-string */
    public function port(): ?string
    {
        return $this->port;
    }

    /** @return null|class-string */
    public function adapter(): ?string
    {
        return $this->adapter;
    }

    /**
     * @return array{
     *     type: string,
     *     module: class-string<Module>,
     *     capability: class-string<Capability>,
     *     evidence: string,
     *     port: null|class-string,
     *     adapter: null|class-string
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'module' => $this->module,
            'capability' => $this->capability,
            'evidence' => $this->evidence,
            'port' => $this->port,
            'adapter' => $this->adapter,
        ];
    }
}
