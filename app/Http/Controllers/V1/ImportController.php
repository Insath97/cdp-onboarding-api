<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkImportRequest;
use App\Services\BulkImportService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    protected BulkImportService $importService;

    public function __construct(BulkImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Define middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Import Index', only: ['index', 'listTables', 'downloadTemplate']),
            new Middleware('permission:Bulk Import', only: ['import']),
        ];
    }

    /**
     * List table names for frontend select box.
     */
    public function listTables(): JsonResponse
    {
        try {
            $configs = $this->importService->getImportableConfig();
            $tables = array_map(function ($table) {
                return [
                    'id' => $table,
                    'name' => ucwords(str_replace('_', ' ', $table)),
                ];
            }, array_keys($configs));

            return response()->json([
                'status' => 'success',
                'data' => array_values($tables),
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('Error', 'Import', "Bulk import table list failure: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve table list.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * List all importable tables and their required attribute headers.
     */
    public function index(): JsonResponse
    {
        try {
            $tables = $this->importService->getImportableTables();

            return response()->json([
                'status' => 'success',
                'data' => $tables,
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('Error', 'Import', "Bulk import catalog failure: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve importable table catalog.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Download CSV template for selected table with actual attribute headers and sample row.
     */
    public function downloadTemplate(string $table): StreamedResponse|JsonResponse
    {
        $template = $this->importService->getTemplateData($table);

        if (! $template) {
            return response()->json([
                'status' => 'error',
                'message' => "Template for table '{$table}' is not available.",
            ], 404);
        }

        $response = new StreamedResponse(function () use ($template) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, $template['headers']);
            fputcsv($handle, $template['sample']);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $template['filename'] . '"');

        return $response;
    }

    /**
     * Handle bulk import for system tables.
     */
    public function import(BulkImportRequest $request, string $table): JsonResponse
    {
        try {
            $file = $request->file('file');

            $this->logActivity('Info', 'Import', "Bulk import started for table: {$table}", [
                'user_id' => Auth::id(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            $results = $this->importService->import($file, $table);

            $this->logActivity('Info', 'Import', "Bulk import completed for table: {$table}", [
                'user_id' => Auth::id(),
                'imported' => $results['imported'],
                'failed' => $results['failed'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Import process completed.',
                'data' => $results,
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('Error', 'Import', "Bulk import failure: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Import failed due to a system error.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
