<?php

namespace App\Services;

use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    use ActivityLogTrait;

    /**
     * Export the database to a SQL file.
     *
     * @return string Path to the generated SQL file
     * @throws \Exception
     */
    public function export(): string
    {
        $connection = config('database.default');
        $connections = config('database.connections');
        $config = $connections[$connection] ?? null;

        if (!$config || ($config['driver'] ?? '') !== 'mysql') {
            throw new \Exception("Export is only supported for MySQL/MariaDB databases.");
        }

        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $filename = "backup-" . date('Y-m-d-H-i-s') . ".sql";
        $directory = storage_path('app/backups');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $filename;

        // Determine mysqldump executable path
        $mysqldump = 'mysqldump';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Check common Laragon MySQL paths if mysqldump is not in system PATH
            $laragonGlob = glob('C:\\laragon\\bin\\mysql\\mysql-*\\bin\\mysqldump.exe');
            if (!empty($laragonGlob) && file_exists($laragonGlob[0])) {
                $mysqldump = '"' . $laragonGlob[0] . '"';
            }
        }

        // Construct the mysqldump command
        $command = sprintf(
            '%s --user=%s %s --host=%s --port=%s %s > %s',
            $mysqldump,
            escapeshellarg($username),
            $password !== null && $password !== '' ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($database),
            escapeshellarg($filePath)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->logActivity(
                'DATABASE_EXPORT_FAILED',
                'Database',
                'Database export failed',
                ['output' => $output, 'exit_code' => $returnVar],
                'error'
            );
            throw new \Exception("Database export failed with exit code {$returnVar}. Ensure mysqldump is in system PATH or Laragon environment.");
        }

        $this->logActivity(
            'DATABASE_EXPORT_SUCCESS',
            'Database',
            "Database exported successfully: {$filename}",
            ['file_name' => $filename, 'file_path' => $filePath]
        );

        return $filePath;
    }
}
