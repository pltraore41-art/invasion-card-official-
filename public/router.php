<?php
declare(strict_types=1);

$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedFile = realpath(__DIR__ . (is_string($requestedPath) ? $requestedPath : '/'));
$publicRoot = realpath(__DIR__);
if (
    $requestedPath !== '/'
    && $requestedFile !== false
    && $publicRoot !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}
require __DIR__ . '/index.php';
