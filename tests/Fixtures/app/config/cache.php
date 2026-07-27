<?php

// Minimal cache config for Feature tests. The Storage facade instantiates Cache, which
// reads cache.stores[default]. The in-framework 'array' driver has no external deps.
return [
    'default' => 'array',
    'stores' => [
        'array' => [
            'driver' => 'array',
        ],
    ],
];
