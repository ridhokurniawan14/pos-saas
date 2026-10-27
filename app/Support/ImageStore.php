<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStore
{
    /** Simpan gambar dari data URL (base64) ke disk public, kembalikan path-nya. */
    public static function fromDataUrl(string $dataUrl, string $dir, int $maxBytes = 2097152): string
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#s', $dataUrl, $m)) {
            throw ValidationException::withMessages(['image_data' => 'Format gambar tidak valid.']);
        }

        $binary = base64_decode($m[2], true);

        if ($binary === false || strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages(['image_data' => 'Gambar rusak atau terlalu besar.']);
        }

        $info = @getimagesizefromstring($binary);
        $ext = match ($info[2] ?? null) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };

        if (! $ext) {
            throw ValidationException::withMessages(['image_data' => 'File bukan gambar yang valid.']);
        }

        $path = trim($dir, '/') . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
