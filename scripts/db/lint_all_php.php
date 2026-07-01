<?php

declare(strict_types=1);

$root = 'c:/xampp/htdocs/du_an2';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$checked = 0;
$bad = [];

foreach ($iterator as $fileInfo) {
    $path = str_replace('\\', '/', $fileInfo->getPathname());
    if (substr($path, -4) !== '.php') {
        continue;
    }

    if (
        strpos($path, '/vendor/') !== false ||
        strpos($path, '/storage/') !== false ||
        strpos($path, '/.git/') !== false
    ) {
        continue;
    }

    $checked++;
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    $output = shell_exec($cmd) ?? '';

    if (strpos($output, 'No syntax errors detected') === false) {
        $bad[] = [
            'file' => $path,
            'output' => trim($output),
        ];
    }
}

echo 'CHECKED=' . $checked . PHP_EOL;
echo 'BAD=' . count($bad) . PHP_EOL;

foreach ($bad as $entry) {
    echo $entry['file'] . PHP_EOL;
    echo $entry['output'] . PHP_EOL;
}
