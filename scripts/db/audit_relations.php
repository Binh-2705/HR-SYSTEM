<?php

declare(strict_types=1);

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'quanlynhansu';

$conn = @mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    fwrite(STDERR, 'DB connect failed: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

mysqli_set_charset($conn, 'utf8mb4');

function scalarCount(mysqli $conn, string $sql): int
{
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        throw new RuntimeException('Query failed: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return (int) ($row['c'] ?? 0);
}

function printRows(mysqli $conn, string $title, string $sql): void
{
    echo PHP_EOL . '[' . $title . ']' . PHP_EOL;

    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        echo 'ERR:' . mysqli_error($conn) . PHP_EOL;
        return;
    }

    if (mysqli_num_rows($result) === 0) {
        echo '(none)' . PHP_EOL;
        mysqli_free_result($result);
        return;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    mysqli_free_result($result);
}

$tables = [
    'nhanvien',
    'hosonhanvien',
    'phongban',
    'chucvu',
    'phancong',
    'chamcong',
    'bangluong',
    'hopdong',
    'bacluong',
    'ngachluong',
    'dottuyendung',
    'khoadaotao',
    'baocao',
];

$orphanChecks = [
    'phancong_orphan_nhanvien' => "SELECT COUNT(*) c FROM phancong pc LEFT JOIN nhanvien nv ON nv.MaNV = pc.MaNV WHERE nv.MaNV IS NULL",
    'phancong_orphan_phongban' => "SELECT COUNT(*) c FROM phancong pc LEFT JOIN phongban pb ON pb.MaPB = pc.MaPB WHERE pb.MaPB IS NULL",
    'phancong_orphan_chucvu' => "SELECT COUNT(*) c FROM phancong pc LEFT JOIN chucvu cv ON cv.MaCV = pc.MaCV WHERE cv.MaCV IS NULL",
    'hosonhanvien_orphan_phongban' => "SELECT COUNT(*) c FROM hosonhanvien hs LEFT JOIN phongban pb ON pb.MaPB = hs.MaPB WHERE hs.MaPB IS NOT NULL AND pb.MaPB IS NULL",
    'chamcong_orphan_nhanvien' => "SELECT COUNT(*) c FROM chamcong cc LEFT JOIN nhanvien nv ON nv.MaNV = cc.MaNV WHERE nv.MaNV IS NULL",
    'hopdong_orphan_nhanvien' => "SELECT COUNT(*) c FROM hopdong hd LEFT JOIN nhanvien nv ON nv.MaNV = hd.MaNV WHERE nv.MaNV IS NULL",
    'hopdong_orphan_bacluong' => "SELECT COUNT(*) c FROM hopdong hd LEFT JOIN bacluong bl ON bl.MaBac = hd.MaBac WHERE bl.MaBac IS NULL",
    'nhanvien_orphan_bacluong' => "SELECT COUNT(*) c FROM nhanvien nv LEFT JOIN bacluong bl ON bl.MaBac = nv.MaBac WHERE nv.MaBac IS NOT NULL AND bl.MaBac IS NULL",
    'bangluong_orphan_nhanvien' => "SELECT COUNT(*) c FROM bangluong b LEFT JOIN nhanvien nv ON nv.MaNV = b.MaNV WHERE nv.MaNV IS NULL",
];

$completenessChecks = [
    'nhanvien_without_hosonhanvien' => "SELECT COUNT(*) c FROM nhanvien nv LEFT JOIN hosonhanvien hs ON hs.MaNV = nv.MaNV WHERE hs.MaNV IS NULL",
    'nhanvien_without_phancong' => "SELECT COUNT(*) c FROM nhanvien nv LEFT JOIN phancong pc ON pc.MaNV = nv.MaNV WHERE pc.MaNV IS NULL",
    'hosonhanvien_null_mapb' => "SELECT COUNT(*) c FROM hosonhanvien WHERE MaPB IS NULL",
    'hosonhanvien_null_macv' => "SELECT COUNT(*) c FROM hosonhanvien WHERE MaCV IS NULL",
    'dottuyendung_without_hosoungtuyen' => "SELECT COUNT(*) c FROM dottuyendung d LEFT JOIN hosoungtuyen h ON h.MaDTD = d.MaDTD WHERE h.MaHS IS NULL",
    'khoadaotao_without_thamgiadaotao' => "SELECT COUNT(*) c FROM khoadaotao k LEFT JOIN thamgiadaotao t ON t.MaKDT = k.MaKDT WHERE t.MaTGDT IS NULL",
    'nhanvien_without_bangluong' => "SELECT COUNT(*) c FROM nhanvien nv LEFT JOIN bangluong b ON b.MaNV = nv.MaNV WHERE b.MaBL IS NULL",
    'nhanvien_without_chamcong' => "SELECT COUNT(*) c FROM nhanvien nv LEFT JOIN chamcong c ON c.MaNV = nv.MaNV WHERE c.MaCC IS NULL",
];

echo "[TABLE_COUNTS]" . PHP_EOL;
foreach ($tables as $table) {
    try {
        $count = scalarCount($conn, "SELECT COUNT(*) c FROM {$table}");
        echo $table . ':' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $table . ':ERR:' . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "[ORPHAN_CHECKS]" . PHP_EOL;
foreach ($orphanChecks as $name => $sql) {
    try {
        $count = scalarCount($conn, $sql);
        echo $name . ':' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $name . ':ERR:' . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "[COMPLETENESS_CHECKS]" . PHP_EOL;
foreach ($completenessChecks as $name => $sql) {
    try {
        $count = scalarCount($conn, $sql);
        echo $name . ':' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $name . ':ERR:' . $e->getMessage() . PHP_EOL;
    }
}

printRows(
    $conn,
    'DETAILS_NHANVIEN_WITHOUT_PHANCONG',
    "SELECT nv.MaNV, nv.HoTen FROM nhanvien nv LEFT JOIN phancong pc ON pc.MaNV = nv.MaNV WHERE pc.MaNV IS NULL ORDER BY nv.MaNV"
);

printRows(
    $conn,
    'DETAILS_HOSONHANVIEN_NULL_MAPB',
    "SELECT MaHoSo, MaNV FROM hosonhanvien WHERE MaPB IS NULL ORDER BY MaHoSo"
);

printRows(
    $conn,
    'DETAILS_HOSONHANVIEN_NULL_MACV',
    "SELECT MaHoSo, MaNV FROM hosonhanvien WHERE MaCV IS NULL ORDER BY MaHoSo"
);

printRows(
    $conn,
    'DETAILS_KHOADAOTAO_WITHOUT_PARTICIPANTS_TOP10',
    "SELECT k.MaKDT, k.TenKhoaDaoTao FROM khoadaotao k LEFT JOIN thamgiadaotao t ON t.MaKDT = k.MaKDT WHERE t.MaTGDT IS NULL ORDER BY k.MaKDT LIMIT 10"
);

printRows(
    $conn,
    'DETAILS_DOTTUYENDUNG_WITHOUT_HOSO_TOP10',
    "SELECT d.MaDTD, d.TenDotTuyenDung FROM dottuyendung d LEFT JOIN hosoungtuyen h ON h.MaDTD = d.MaDTD WHERE h.MaHS IS NULL ORDER BY d.MaDTD LIMIT 10"
);

mysqli_close($conn);
