<?php

return [
    app\middleware\CorsMiddleware::class,
    app\middleware\AuthenticationMiddleware::class,
    app\middleware\AuthorizationMiddleware::class,
];
