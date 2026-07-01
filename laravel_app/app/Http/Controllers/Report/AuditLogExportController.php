<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;

use App\Services\AppAuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogExportController extends Controller
{
    public function __construct(private AppAuditLogService $auditLogService)
    {
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->auditLogService->readFilteredRows(
            strtoupper(trim((string) $request->query('level', ''))),
            trim((string) $request->query('q', '')),
        );

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['time', 'level', 'message', 'context']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['time'], $row['level'], $row['message'], $row['context']]);
            }
            fclose($out);
        }, 'audit-log-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportJson(Request $request)
    {
        $level = strtoupper(trim((string) $request->query('level', '')));
        $q = trim((string) $request->query('q', ''));
        $rows = $this->auditLogService->readFilteredRows($level, $q);

        return response()->json([
            'exportedAt' => now()->toIso8601String(),
            'filters' => ['level' => $level, 'q' => $q],
            'count' => count($rows),
            'logs' => $rows,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Ymd-His') . '.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}