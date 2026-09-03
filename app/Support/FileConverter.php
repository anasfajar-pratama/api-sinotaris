<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class FileConverter
{
    /**
     * Simpan file upload. Untuk gambar raster (jpg/jpeg/png) hasilnya dikonversi
     * ke webp; jenis lain (pdf/docx) disimpan apa adanya.
     */
    public static function store(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        if (static::isImage($file)) {
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $path = trim($folder, '/') . '/' . static::safeName($name) . '-' . uniqid() . '.webp';
            Storage::disk($disk)->put($path, static::toWebp($file));

            return $path;
        }

        return $file->store($folder, $disk);
    }

    public static function isImage(UploadedFile $file): bool
    {
        return in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true);
    }

    public static function toWebp(UploadedFile $file): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file->getRealPath());

        return (string) $image->toWebp(82);
    }

    private static function safeName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', $name) ?? '';
        return strtolower(trim($name, '-')) ?: 'file';
    }
}
