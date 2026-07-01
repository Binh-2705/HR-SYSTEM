<?php

declare(strict_types=1);

$baseUrl = 'http://localhost';
$username = 'binhnguyen';
$password = '12345678';

$cookieFile = tempnam(sys_get_temp_dir(), 'duan2_cookie_');
if ($cookieFile === false) {
    fwrite(STDERR, "Failed to create cookie file\n");
    exit(1);
}

$ch = curl_init();
if ($ch === false) {
    fwrite(STDERR, "Failed to initialize cURL\n");
    exit(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => ['User-Agent: debug-checker'],
]);

curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
$loginPageResponse = curl_exec($ch);
if ($loginPageResponse === false) {
    fwrite(STDERR, 'GET /login failed: ' . curl_error($ch) . "\n");
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$loginPageStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$loginPageBody = substr((string) $loginPageResponse, $headerSize);

if ($loginPageStatus !== 200) {
    fwrite(STDERR, "GET /login returned {$loginPageStatus}\n");
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

if (!preg_match('/name="_token"\s+value="([^"]+)"/', $loginPageBody, $matches)) {
    fwrite(STDERR, "CSRF token not found\n");
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

$token = $matches[1];
$postFields = http_build_query([
    '_token' => $token,
    'TenDangNhap' => $username,
    'MatKhau' => $password,
]);

curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '/login',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: debug-checker',
    ],
]);

$loginSubmitResponse = curl_exec($ch);
if ($loginSubmitResponse === false) {
    fwrite(STDERR, 'POST /login failed: ' . curl_error($ch) . "\n");
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

$loginSubmitStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$loginSubmitHeaderSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$loginSubmitHeaders = substr((string) $loginSubmitResponse, 0, $loginSubmitHeaderSize);

$location = '';
foreach (explode("\r\n", $loginSubmitHeaders) as $headerLine) {
    if (stripos($headerLine, 'Location:') === 0) {
        $location = trim(substr($headerLine, 9));
        break;
    }
}

// Use a fresh handle for dashboard request to avoid any residual POST state.
$dashboardCh = curl_init();
if ($dashboardCh === false) {
    fwrite(STDERR, "Failed to initialize cURL for dashboard request\n");
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

curl_setopt_array($dashboardCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => ['User-Agent: debug-checker'],
    CURLOPT_URL => $baseUrl . '/dashboard',
    CURLOPT_HTTPGET => true,
]);

$dashboardResponse = curl_exec($dashboardCh);
if ($dashboardResponse === false) {
    fwrite(STDERR, 'GET /dashboard failed: ' . curl_error($dashboardCh) . "\n");
    curl_close($dashboardCh);
    curl_close($ch);
    @unlink($cookieFile);
    exit(1);
}

$dashboardStatus = (int) curl_getinfo($dashboardCh, CURLINFO_RESPONSE_CODE);
$dashboardHeaderSize = (int) curl_getinfo($dashboardCh, CURLINFO_HEADER_SIZE);
$dashboardBody = substr((string) $dashboardResponse, $dashboardHeaderSize);

curl_close($dashboardCh);
curl_close($ch);
@unlink($cookieFile);

echo 'LOGIN_POST_STATUS=' . $loginSubmitStatus . PHP_EOL;
echo 'LOGIN_POST_LOCATION=' . $location . PHP_EOL;
echo 'DASHBOARD_STATUS=' . $dashboardStatus . PHP_EOL;
echo 'DASHBOARD_HAS_500_TEXT=' . (str_contains($dashboardBody, '500 - Lỗi hệ thống') ? '1' : '0') . PHP_EOL;
