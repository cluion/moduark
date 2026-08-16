<?php

declare(strict_types=1);

return [
    'path' => app_path('Modules'),

    'architecture' => [
        // The beta currently guarantees the Level 0, Level 1, and Level 2 rule sets.
        'level' => 1,

        // Existing violations can be adopted explicitly with module:baseline.
        'baseline' => base_path('moduark-baseline.json'),

        // Reviewed, narrowly-scoped exceptions can be tracked with reasons here.
        'suppressions' => base_path('moduark-suppressions.json'),

        // Only declare exceptions here. Unlisted rules inherit the level preset.
        'rules' => [],
    ],
];
