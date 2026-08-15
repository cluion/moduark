<?php

declare(strict_types=1);

namespace Cluion\Moduark\Graph;

use Cluion\Moduark\Capability;
use InvalidArgumentException;

final readonly class CapabilityGraphNode
{
    /** @var class-string<Capability> */
    private string $capability;

    /**
     * @param string $capability
     */
    public function __construct(
        private string $name,
        string $capability,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A Capability graph node name must not be empty.');
        }

        if ($capability === Capability::class || ! is_a($capability, Capability::class, true)) {
            throw new InvalidArgumentException(
                'A Capability graph node class must extend Capability.',
            );
        }

        $this->capability = $capability;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return class-string<Capability> */
    public function capability(): string
    {
        return $this->capability;
    }

    /** @return array{name: string, class: class-string<Capability>} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->capability,
        ];
    }
}
