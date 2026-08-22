<?php

declare(strict_types=1);

return [
    'path' => app_path('Modules'),

    'architecture' => [
        // Levels 0 through 2 are candidate 1.0 Stable presets; Level 3 is Preview.
        'level' => 1,

        // Existing violations can be adopted explicitly with module:baseline.
        'baseline' => base_path('moduark-baseline.json'),

        // Reviewed, narrowly-scoped exceptions can be tracked with reasons here.
        'suppressions' => base_path('moduark-suppressions.json'),

        // Only declare exceptions here. Unlisted rules inherit the level preset.
        'rules' => [],
    ],
];
