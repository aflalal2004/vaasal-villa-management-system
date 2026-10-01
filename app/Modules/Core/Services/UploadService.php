<?php

namespace App\Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Secure file uploads: whitelist extensions, verify real MIME type from file content,
 * confirm images actually decode, randomise names and keep sensitive files (ID scans,
 * payment proofs) on the private disk outside the web root.
 */
class UploadService
{
    public const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const DOC_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public static function image(UploadedFile $file, string $dir): string
    {
        return self::store($file, $dir, 'public', self::IMAGE_MIMES);
    }

    /** Stored on the private "local" disk; served only through authorised controllers. */
    public static function privateDocument(UploadedFile $file, string $dir): string
    {
        return self::store($file, $dir, 'local', self::DOC_MIMES);
    }

    private static function store(UploadedFile $file, string $dir, string $disk, array $allowed): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The upload failed. Please try again.']);
        }
        if ($file->getSize() > config('vaasal.security.upload_max_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'The file is larger than '.(config('vaasal.security.upload_max_kb') / 1024).' MB.']);
        }
        $mime = $file->getMimeType(); // detected from content (finfo), not the client header
        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages(['file' => 'Unsupported file type. Allowed: '.implode(', ', array_unique($allowed)).'.']);
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['file' => 'The image could not be read. Upload a valid JPG, PNG or WebP.']);
        }

        $name = now()->format('Ym').'/'.Str::random(32).'.'.$allowed[$mime];
        return $file->storeAs(trim($dir, '/'), $name, $disk);
    }
}
