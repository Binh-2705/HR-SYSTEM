<?php

use Illuminate\Support\Str;

return [

    

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'hr_service' => [
            'driver' => env('HR_DB_CONNECTION', 'mysql'),
            'url' => env('HR_DATABASE_URL'),
            'host' => env('HR_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('HR_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('HR_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('HR_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('HR_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('HR_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('HR_DB_CHARSET', 'utf8mb4'),
            'collation' => env('HR_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('HR_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('HR_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'payroll_service' => [
            'driver' => env('PAYROLL_DB_CONNECTION', 'mysql'),
            'url' => env('PAYROLL_DATABASE_URL'),
            'host' => env('PAYROLL_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('PAYROLL_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('PAYROLL_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('PAYROLL_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('PAYROLL_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('PAYROLL_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('PAYROLL_DB_CHARSET', 'utf8mb4'),
            'collation' => env('PAYROLL_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('PAYROLL_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('PAYROLL_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'recruitment_service' => [
            'driver' => env('RECRUITMENT_DB_CONNECTION', 'mysql'),
            'url' => env('RECRUITMENT_DATABASE_URL'),
            'host' => env('RECRUITMENT_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('RECRUITMENT_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('RECRUITMENT_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('RECRUITMENT_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('RECRUITMENT_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('RECRUITMENT_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('RECRUITMENT_DB_CHARSET', 'utf8mb4'),
            'collation' => env('RECRUITMENT_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('RECRUITMENT_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('RECRUITMENT_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'attendance_service' => [
            'driver' => env('ATTENDANCE_DB_CONNECTION', 'mysql'),
            'url' => env('ATTENDANCE_DATABASE_URL'),
            'host' => env('ATTENDANCE_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('ATTENDANCE_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('ATTENDANCE_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('ATTENDANCE_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('ATTENDANCE_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('ATTENDANCE_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('ATTENDANCE_DB_CHARSET', 'utf8mb4'),
            'collation' => env('ATTENDANCE_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('ATTENDANCE_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('ATTENDANCE_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'training_service' => [
            'driver' => env('TRAINING_DB_CONNECTION', 'mysql'),
            'url' => env('TRAINING_DATABASE_URL'),
            'host' => env('TRAINING_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('TRAINING_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('TRAINING_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('TRAINING_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('TRAINING_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('TRAINING_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('TRAINING_DB_CHARSET', 'utf8mb4'),
            'collation' => env('TRAINING_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('TRAINING_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('TRAINING_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'reporting_service' => [
            'driver' => env('REPORTING_DB_CONNECTION', 'mysql'),
            'url' => env('REPORTING_DATABASE_URL'),
            'host' => env('REPORTING_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('REPORTING_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('REPORTING_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('REPORTING_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('REPORTING_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('REPORTING_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('REPORTING_DB_CHARSET', 'utf8mb4'),
            'collation' => env('REPORTING_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('REPORTING_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('REPORTING_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'chatbot_service' => [
            'driver' => env('CHATBOT_DB_CONNECTION', 'mysql'),
            'url' => env('CHATBOT_DATABASE_URL'),
            'host' => env('CHATBOT_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('CHATBOT_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('CHATBOT_DB_DATABASE', env('DB_DATABASE', 'forge')),
            'username' => env('CHATBOT_DB_USERNAME', env('DB_USERNAME', 'forge')),
            'password' => env('CHATBOT_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('CHATBOT_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('CHATBOT_DB_CHARSET', 'utf8mb4'),
            'collation' => env('CHATBOT_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('CHATBOT_DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('CHATBOT_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA')),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    'migrations' => 'migrations',

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
