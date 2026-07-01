<?php
$dsn = 'mysql:host=db;dbname=quanlynhansu;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$username = $argv[1] ?? 'binhnguyen';
$password = $argv[2] ?? '12345678';

$stmt = $pdo->prepare('SELECT MatKhau FROM taikhoan WHERE TenDangNhap = ? LIMIT 1');
$stmt->execute([$username]);
$hash = (string) $stmt->fetchColumn();

var_export(password_verify($password, $hash));
echo PHP_EOL;
