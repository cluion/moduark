<?php

declare(strict_types=1);

namespace Cluion\Moduark\Generation;

enum GenerationPlanFormat: string
{
    case Text = 'text';
    case Json = 'json';
}
