<?php

// Minimal filesystem config for Feature tests. The HTTP bootstrap (Http\Server) eagerly
// instantiates the File/Storage facades, which read filesystems.disks[default].
return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver'     => 'local',
            'root'       => sys_get_temp_dir(),
            'visibility' => 'public',
        ],
    ],
];
