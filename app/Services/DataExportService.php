<?php

namespace App\Services;

use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataExportService
{
    use ActivityLogTrait;

    /**
     * Internal system tables that should not be exported.
     *
     * @var array<string>
     */
    protected array $excludedTables = [
        'migrations',
        'failed_jobs',
        'job_batches',
        'jobs',
        'personal_access_tokens',
        'sessions',
        'password_reset_tokens',
        'cache',
        'cache_locks',
    ];

    /**
     * Get all exportable tables from the database with record counts.
     *
     * @return array<array{table: string, record_count: int}>
     */
    public function getExportableTableList(): array
    {
        $tables = $this->resolveExportableTables();
        $result = [];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $result[] = [
                'table' => $table,
                'record_count' => $count,
            ];
        }

        return $result;
    }

    /**
     * Export a single table to an array format ready for CSV streaming.
     *
     * @param string $table
     * @return array{table: string, record_count: int, headers: array, data: array, filename: string}|null
     */
    public function exportTableToCsv(string $table): ?array
    {
        $exportableTables = $this->resolveExportableTables();

        if (!in_array($table, $exportableTables, true) || !Schema::hasTable($table)) {
            return null;
        }

        $records = DB::table($table)->get();
        $count = $records->count();

        if ($count === 0) {
            $headers = Schema::getColumnListing($table);
            $data = [];
        } else {
            $firstRow = (array) $records->first();
            $headers = array_keys($firstRow);
            $data = $records->map(fn ($row) => array_values((array) $row))->toArray();
        }

        $filename = "{$table}_export_" . date('Y-m-d_H-i-s') . ".csv";

        return [
            'table' => $table,
            'record_count' => $count,
            'headers' => $headers,
            'data' => $data,
            'filename' => $filename,
        ];
    }

    /**
     * Export all exportable tables as a single multi-sheet Excel (.xls) file.
     *
     * @return string Absolute file path to the generated Excel file
     */
    public function exportAllToExcel(): string
    {
        $directory = storage_path('app/exports');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "all_tables_export_" . date('Y-m-d_H-i-s') . ".xls";
        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;

        $handle = fopen($filePath, 'w');

        // Write XML Spreadsheet header
        fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        fwrite($handle, "<?mso-application progid=\"Excel.Sheet\"?>\n");
        fwrite($handle, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
        fwrite($handle, " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n");
        fwrite($handle, " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n");
        fwrite($handle, " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
        fwrite($handle, " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n");
        fwrite($handle, " <Styles>\n");
        fwrite($handle, "  <Style ss:ID=\"Header\">\n");
        fwrite($handle, "   <Font ss:Bold=\"1\"/>\n");
        fwrite($handle, "  </Style>\n");
        fwrite($handle, " </Styles>\n");

        $tables = $this->resolveExportableTables();

        foreach ($tables as $table) {
            // Sheet names in Excel: max 31 characters, cannot contain / \ ? * : [ ]
            $sheetName = substr(str_replace(['/', '\\', '?', '*', ':', '[', ']'], '_', $table), 0, 31);
            $records = DB::table($table)->get();

            fwrite($handle, " <Worksheet ss:Name=\"" . htmlspecialchars($sheetName, ENT_XML1, 'UTF-8') . "\">\n");
            fwrite($handle, "  <Table>\n");

            if ($records->isEmpty()) {
                $columns = Schema::getColumnListing($table);
                fwrite($handle, "   <Row ss:StyleID=\"Header\">\n");
                foreach ($columns as $column) {
                    fwrite($handle, "    <Cell><Data ss:Type=\"String\">" . htmlspecialchars((string) $column, ENT_XML1, 'UTF-8') . "</Data></Cell>\n");
                }
                fwrite($handle, "   </Row>\n");
            } else {
                $firstRow = (array) $records->first();
                $columns = array_keys($firstRow);

                fwrite($handle, "   <Row ss:StyleID=\"Header\">\n");
                foreach ($columns as $column) {
                    fwrite($handle, "    <Cell><Data ss:Type=\"String\">" . htmlspecialchars((string) $column, ENT_XML1, 'UTF-8') . "</Data></Cell>\n");
                }
                fwrite($handle, "   </Row>\n");

                foreach ($records as $row) {
                    fwrite($handle, "   <Row>\n");
                    foreach ((array) $row as $val) {
                        $cellValue = is_null($val) ? '' : (is_scalar($val) ? (string) $val : json_encode($val));
                        fwrite($handle, "    <Cell><Data ss:Type=\"String\">" . htmlspecialchars($cellValue, ENT_XML1, 'UTF-8') . "</Data></Cell>\n");
                    }
                    fwrite($handle, "   </Row>\n");
                }
            }

            fwrite($handle, "  </Table>\n");
            fwrite($handle, " </Worksheet>\n");
        }

        fwrite($handle, "</Workbook>\n");
        fclose($handle);

        return $filePath;
    }

    /**
     * Resolve list of exportable tables excluding system/internal tables.
     *
     * @return array<string>
     */
    protected function resolveExportableTables(): array
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        $rawTables = $connection->select('SHOW TABLES');
        $key = "Tables_in_{$databaseName}";
        $tables = [];

        foreach ($rawTables as $rawTable) {
            $tableObj = (array) $rawTable;
            $tableName = $tableObj[$key] ?? array_values($tableObj)[0] ?? null;

            if ($tableName && !in_array($tableName, $this->excludedTables, true)) {
                $tables[] = $tableName;
            }
        }

        sort($tables);

        return $tables;
    }
}
