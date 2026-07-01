<?php

require_once __DIR__ . '/connection.php';

function out(string $text): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function err(string $text): void
{
    fwrite(STDERR, $text . PHP_EOL);
}

$rootDir = dirname(__DIR__, 2);
$migrationsDir = $rootDir . '/migrations';

if (!is_dir($migrationsDir)) {
    err('[ERROR] Missing migrations directory: ' . $migrationsDir);
    exit(1);
}

$createTableSql = "CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    checksum CHAR(64) NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!$conn->query($createTableSql)) {
    err('[ERROR] Cannot ensure schema_migrations table: ' . $conn->error);
    exit(1);
}

$applied = [];
$res = $conn->query('SELECT filename, checksum FROM schema_migrations');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $applied[(string) $row['filename']] = (string) $row['checksum'];
    }
}

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$pending = [];
foreach ($files as $filePath) {
    $filename = basename($filePath);
    $checksum = hash_file('sha256', $filePath);

    if (!isset($applied[$filename])) {
        $pending[] = [$filename, $filePath, $checksum];
        continue;
    }

    if ($applied[$filename] !== $checksum) {
        err('[ERROR] Checksum mismatch for applied migration: ' . $filename);
        err('        Applied: ' . $applied[$filename]);
        err('        Current: ' . $checksum);
        exit(1);
    }
}

if (empty($pending)) {
    out('[OK] No pending migrations.');
    exit(0);
}

out('[INFO] Pending migrations: ' . count($pending));

foreach ($pending as [$filename, $filePath, $checksum]) {
    out('[INFO] Applying: ' . $filename);
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        err('[ERROR] Cannot read migration file: ' . $filePath);
        exit(1);
    }

    if (!$conn->multi_query($sql)) {
        err('[ERROR] Migration failed: ' . $filename);
        err('        SQL error: ' . $conn->error);
        exit(1);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->error) {
        err('[ERROR] Migration failed while consuming result sets: ' . $filename);
        err('        SQL error: ' . $conn->error);
        exit(1);
    }

    $insertStmt = $conn->prepare('INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)');
    if (!$insertStmt) {
        err('[ERROR] Cannot record migration: ' . $filename . ' | ' . $conn->error);
        exit(1);
    }

    $insertStmt->bind_param('ss', $filename, $checksum);
    if (!$insertStmt->execute()) {
        err('[ERROR] Cannot record migration execution: ' . $filename . ' | ' . $insertStmt->error);
        $insertStmt->close();
        exit(1);
    }
    $insertStmt->close();

    out('[OK] Applied: ' . $filename);
}

out('[DONE] Migrations completed successfully.');