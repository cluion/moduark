<?php

declare(strict_types=1);

return [
    // null selects nwidart's Module root when available, otherwise app/Modules.
    'path' => null,

    'architecture' => [
        // Levels 0 through 2 are Stable presets; Level 3 is Preview.
        'level' => 1,

        // Existing violations can be adopted explicitly with moduark:baseline.
        'baseline' => base_path('moduark-baseline.json'),

        // Reviewed, narrowly-scoped exceptions can be tracked with reasons here.
        'suppressions' => base_path('moduark-suppressions.json'),

        // Only declare exceptions here. Unlisted rules inherit the level preset.
        'rules' => [],
    ],
];
