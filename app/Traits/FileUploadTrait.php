<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait FileUploadTrait
{
    public function handleFileUpload(
        Request $request,
        string $fieldName,
        ?string $oldPath = null,
        string $module = 'general',
        string $prefix = ''
    ): ?string {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        // Delete old file if exists
        $this->deleteFile($oldPath);

        $file = $request->file($fieldName);
        $extension = $file->getClientOriginalExtension();

        // Generate filename with optional prefix
        $fileName = $prefix
            ? $prefix . '.' . $extension
            : Str::random(25) . '.' . $extension;

        $directory = "uploads/{$module}";
        $filePath = "{$directory}/{$fileName}";

        // Create directory if not exists
        if (!File::exists(public_path($directory))) {
            File::makeDirectory(public_path($directory), 0755, true);
        }

        $file->move(public_path($directory), $fileName);

        return $filePath;
    }

    /**
     * Handle multiple file uploads
     */
    public function handleMultipleFileUpload(
        Request $request,
        string $fieldName,
        array $oldPaths = [],
        string $module = 'general',
        string $prefix = ''
    ): array {
        if (!$request->hasFile($fieldName)) {
            return [];
        }

        $files = $request->file($fieldName);

        // Ensure it's an array
        if (!is_array($files)) {
            $files = [$files];
        }

        $uploadedPaths = [];

        foreach ($files as $index => $file) {
            // Skip if file is not valid
            if (!$file->isValid()) {
                continue;
            }

            $extension = $file->getClientOriginalExtension();

            // Generate unique filename
            $fileName = $prefix
                ? $prefix . '_' . ($index + 1) . '.' . $extension
                : Str::random(25) . '_' . ($index + 1) . '.' . $extension;

            $directory = "uploads/{$module}";
            $filePath = "{$directory}/{$fileName}";

            // Create directory if not exists
            $this->createDirectory($directory);

            $file->move(public_path($directory), $fileName);
            $uploadedPaths[] = $filePath;
        }

        // Delete old files if new ones were uploaded
        if (!empty($uploadedPaths) && !empty($oldPaths)) {
            foreach ($oldPaths as $oldPath) {
                $this->deleteFile($oldPath);
            }
        }

        return $uploadedPaths;
    }

    /**
     * Helper method to create directory
     */
    private function createDirectory(string $directory): void
    {
        if (!File::exists(public_path($directory))) {
            File::makeDirectory(public_path($directory), 0755, true, true);
        }
    }

    /**
     * Delete a single file
     */
    public function deleteFile(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    /**
     * Delete multiple files
     */
    public function deleteMultipleFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }
}
