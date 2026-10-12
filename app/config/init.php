
<?php

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
