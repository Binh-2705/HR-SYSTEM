<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogBizController extends Controller
{
    private function logFilePath(): string
    {
        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
    }

    public function index(Request $request): JsonResponse
    {
        $levelFilter = strtoupper(trim((string) $request->query('level', '')));
        $q           = trim((string) $request->query('q', ''));

        $logFile = $this->logFilePath();
        $rows    = [];

        if (is_readable($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $lines = array_reverse($lines);

            foreach ($lines as $line) {
                if (!preg_match('/^\[(.*?)\] \[(.*?)\] (.*?) (\{.*\})$/', $line, $matches)) {
                    continue;
                }
                $rows[] = [
                    'time'    => $matches[1],
                    'level'   => strtoupper($matches[2]),
                    'message' => $matches[3],
                    'context' => $matches[4],
                ];
            }
        }

        if ($levelFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['level'] === $levelFilter));
        }

        if ($q !== '') {
            $rows = array_values(array_filter($rows, fn (array $row) => stripos($row['message'], $q) !== false || stripos($row['context'], $q) !== false));
        }

        return response()->json(['ok' => true, 'data' => $rows]);
    }
}
