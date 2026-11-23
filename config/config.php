<?php
declare(strict_types=1);

// CHANGE: Read local database settings from environment variables for XAMPP setup.
define('BASE_URL', rtrim((string) (getenv('BASE_URL') ?: ''), '/'));
define('DB_HOST', (string) (getenv('DB_HOST') ?: '127.0.0.1'));
define('DB_NAME', (string) (getenv('DB_NAME') ?: 'lostfound'));
define('DB_USER', (string) (getenv('DB_USER') ?: 'root'));
$db_password = getenv('DB_PASS');
define('DB_PASS', $db_password === false ? '' : $db_password);
