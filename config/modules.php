<?php

declare(strict_types=1);

return [
    'path' => app_path('Modules'),

    'architecture' => [
        // The beta only guarantees the Level 0 and Level 1 rule sets.
        'level' => 1,

        // Only declare exceptions here. Unlisted rules inherit the level preset.
        'rules' => [],
    ],
];
