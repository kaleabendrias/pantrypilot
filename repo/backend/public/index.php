<?php
declare(strict_types=1);

namespace think;

require __DIR__ . '/../vendor/autoload.php';

$app = new App();
$http = $app->http;

$response = $http->run();
$response->send();
$http->end($response);
