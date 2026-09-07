<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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

        $this->deleteFile($oldPath);

        $file = $request->file($fieldName);
        $extension = $file->getClientOriginalExtension();

        $fileName = $prefix
            ? $prefix . '.' . $extension
            : Str::uuid() . '.' . $extension;

        $directory = "uploads/{$module}";
        $filePath = "{$directory}/{$fileName}";

        $disk = Storage::disk('private');

        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $disk->put($filePath, file_get_contents($file));

        return $filePath;
    }

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

        if (!is_array($files)) {
            $files = [$files];
        }

        $uploadedPaths = [];
        $disk = Storage::disk('private');

        foreach ($files as $index => $file) {
            if (!$file->isValid()) {
                continue;
            }

            $extension = $file->getClientOriginalExtension();

            $fileName = $prefix
                ? $prefix . '_' . ($index + 1) . '.' . $extension
                : Str::uuid() . '_' . ($index + 1) . '.' . $extension;

            $directory = "uploads/{$module}";
            $filePath = "{$directory}/{$fileName}";

            if (!$disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }

            $disk->put($filePath, file_get_contents($file));
            $uploadedPaths[] = $filePath;
        }

        if (!empty($uploadedPaths) && !empty($oldPaths)) {
            foreach ($oldPaths as $oldPath) {
                $this->deleteFile($oldPath);
            }
        }

        return $uploadedPaths;
    }

    public function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    public function deleteMultipleFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }
}
