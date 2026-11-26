<?php

// Only the keys we change; the package merges in its defaults for the rest.
return [

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['tsx'],
    ],

];
