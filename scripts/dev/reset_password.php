<?php
$dsn = 'mysql:host=db;dbname=quanlynhansu;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$username = $argv[1] ?? 'binhnguyen';
$password = $argv[2] ?? '12345678';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE taikhoan SET MatKhau = ?, BuocDoiMatKhau = 0 WHERE TenDangNhap = ?');
$stmt->execute([$hash, $username]);

echo "updated_rows=" . $stmt->rowCount() . PHP_EOL;
echo "hash=" . $hash . PHP_EOL;
