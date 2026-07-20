<?php

declare(strict_types=1);

/**
 * Capture endpoint for the E2E suite, served with PHP's built-in server:
 *
 *   CAPTURE_DIR=... php -S 0.0.0.0:8085 e2e/capture/server.php
 *
 * Postal's webhook and inbound HTTP-endpoint deliveries land here; each
 * request is recorded verbatim (headers + raw body, base64) as one JSON
 * file so the tests can verify the real signatures and replay the exact
 * bytes through the package's own routes.
 */
$dir = getenv('CAPTURE_DIR');

if ($dir === false || $dir === '') {
    http_response_code(500);
    exit('CAPTURE_DIR is not set');
}

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$rawBody = (string) file_get_contents('php://input');

$headers = [];

foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_') && is_string($value)) {
        $headers[str_replace('_', '-', substr($key, 5))] = $value;
    }
}

$record = [
    'captured_at' => microtime(true),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'headers' => $headers,
    'body_b64' => base64_encode($rawBody),
];

$slug = trim(str_replace('/', '_', parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: 'root'), '_');

file_put_contents(
    sprintf('%s/%s-%s.json', rtrim($dir, '/'), $slug, uniqid('', true)),
    json_encode($record, JSON_PRETTY_PRINT)
);

header('Content-Type: application/json');
echo '{"status":"ok"}';
