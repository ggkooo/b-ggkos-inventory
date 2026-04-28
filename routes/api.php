<?php

$routeFile = config('backends.server_role', 'gateway') === 'backend'
    ? __DIR__.'/api-backend.php'
    : __DIR__.'/api-gateway.php';

require $routeFile;
