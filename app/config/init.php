
<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isApiRequest = preg_match('~^/Northlandia_Inventory_System/api(?:/|$)~', $requestPath) === 1;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originParts = $origin !== '' ? parse_url($origin) : false;
$originHost = is_array($originParts) ? strtolower($originParts['host'] ?? '') : '';
$originPort = is_array($originParts) ? ($originParts['port'] ?? null) : null;
$allowedLocalOrigin = is_array($originParts)
    && strtolower($originParts['scheme'] ?? '') === 'http'
    && in_array($originHost, ['localhost', '127.0.0.1'], true)
    && ($originPort === null || ($originPort >= 1 && $originPort <= 65535))
    && !isset($originParts['user'], $originParts['pass'], $originParts['query'], $originParts['fragment'])
    && (!isset($originParts['path']) || $originParts['path'] === '');

if ($isApiRequest && $allowedLocalOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
}

if ($isApiRequest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/Northlandia_Inventory_System');

define('DB_HOST', 'localhost');
define('DB_NAME', 'northlandia_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('Asia/Manila');
