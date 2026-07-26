<?php

// Minimal application config for Feature tests. Fixed key so Encrypter/signing
// behaviour is deterministic across runs.
return [
    'name'      => 'AtomFixture',
    'env'       => 'testing',
    'debug'     => false,
    'url'       => 'http://localhost',
    'key'       => 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', // 32 bytes
    'providers' => [],
];
