<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileUploadHelper
{
    /**
     * Get a unique filename for storage by adding a suffix if it already exists.
     */
    public static function getUniqueFilename(TemporaryUploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $originalName = $file->getClientOriginalName();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $directory = trim($directory, '/');

        $finalName = $originalName;
        $counter = 1;

        while (Storage::disk($disk)->exists("{$directory}/{$finalName}")) {
            $finalName = "{$filename} ({$counter}).{$extension}";
            $counter++;
        }

        return $finalName;
    }
}
