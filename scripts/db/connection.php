<?php

if (!function_exists('loadEnvFromFile')) {
    function loadEnvFromFile($filePath)
    {
        if (!is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($name !== '' && getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

$rootDir = dirname(__DIR__, 2);

if (getenv('DB_HOST') === false) {
    loadEnvFromFile($rootDir . '/.env');
    loadEnvFromFile($rootDir . '/laravel_app/.env');
}

$servername = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$username = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$dbname = getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : 'quanlynhansu';

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die('Khong the ket noi CSDL. Vui long kiem tra cau hinh moi truong.');
}

mysqli_set_charset($conn, 'utf8');