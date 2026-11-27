<?php

// Each role's pages get only the route names they use (`@routes` in app.blade.php), not the whole app's.
$common = ['home', 'dashboard', 'login', 'logout', 'password.*', 'profile.*', 'verification.*'];

return [
    'groups' => [
        'guest' => $common,
        'admin' => [...$common, 'admin.*'],
        'panel' => [...$common, 'panel.*'],
    ],
];
