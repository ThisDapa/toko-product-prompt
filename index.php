<?php
require_once 'routes.php';

$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$url = ltrim($url, '/');

if ($url === '') {
    $url = '';
}

if (isset($routes[$url])) {
    require_once $routes[$url];
} else {
    http_response_code(404);
    echo "404 - Page Not Found";
}