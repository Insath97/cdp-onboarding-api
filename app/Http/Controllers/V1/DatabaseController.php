<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\DatabaseService;
use App\Services\DataExportService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    protected DatabaseService $databaseService;
    protected DataExportService $dataExportService;

    public function __construct(DatabaseService $databaseService, DataExportService $dataExportService)
    {
        $this->databaseService = $databaseService;
        $this->dataExportService = $dataExportService;
    }

    /**
     * Define middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Database Export', only: ['export', 'exportData', 'exportTable', 'exportAllTables']),
        ];
    }

    /**
     * Export the database and download the SQL dump file (Protected).
     */
    public function export(): BinaryFileResponse|JsonResponse
    {
        try {
            $filePath = $this->databaseService->export();
            $filename = basename($filePath);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'Database', "Database export failure: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export database.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * List all exportable tables with record counts.
     */
    public function exportData(): JsonResponse
    {
        try {
            $tables = $this->dataExportService->getExportableTableList();

            return response()->json([
                'status' => 'success',
                'message' => 'Exportable tables retrieved successfully.',
                'data' => $tables,
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'DataExport', "Failed to list exportable tables: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve exportable tables.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Export a single table as CSV with real data.
     */
    public function exportTable(Request $request, string $table): StreamedResponse|JsonResponse
    {
        try {
            $exportData = $this->dataExportService->exportTableToCsv($table);

            if (!$exportData) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Table '{$table}' is not available for export.",
                ], 404);
            }

            $this->logActivity('DATA_EXPORT', 'DataExport', "Exported table: {$table} ({$exportData['record_count']} records)", [
                'table' => $table,
                'record_count' => $exportData['record_count'],
            ]);

            $response = new StreamedResponse(function () use ($exportData) {
                $handle = fopen('php://output', 'w');

                // Add UTF-8 BOM for Microsoft Excel compatibility
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // Write headers
                fputcsv($handle, $exportData['headers']);

                // Write data rows
                foreach ($exportData['data'] as $row) {
                    fputcsv($handle, $row);
                }

                fclose($handle);
            });

            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $exportData['filename'] . '"');

            return $response;

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'DataExport', "Export failed for table {$table}: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => "Failed to export table '{$table}'.",
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Export all tables as a single Excel file with real data.
     */
    public function exportAllTables(): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        try {
            $filePath = $this->dataExportService->exportAllToExcel();
            $filename = basename($filePath);

            $this->logActivity('DATA_EXPORT_ALL', 'DataExport', "Exported all tables to Excel: {$filename}", [
                'file' => $filename,
            ]);

            $response = response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ])->deleteFileAfterSend(true);

            return $response;

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'DataExport', "Export all tables failed: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export all tables.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
