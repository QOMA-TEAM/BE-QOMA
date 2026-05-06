<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Upload gambar ke storage.
     *
     * @param UploadedFile $file    File yang diupload
     * @param string       $folder  Nama folder tujuan (tanpa leading slash)
     * @return string               Path relatif untuk disimpan di DB
     *
     * Contoh:
     * $path = $imageService->upload($request->file('gambar'), 'menu/usaha-id');
     * // return: "menu/usaha-id/uuid.jpg"
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($folder, $filename, 'public');
    }

    /**
     * Hapus gambar dari storage.
     *
     * @param string|null $path  Path relatif dari DB
     */
    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Replace gambar lama dengan yang baru.
     * Hapus lama → upload baru → return path baru.
     *
     * @param UploadedFile $file     File baru
     * @param string|null  $oldPath  Path lama yang akan dihapus
     * @param string       $folder   Folder tujuan
     * @return string                Path baru
     */
    public function replace(UploadedFile $file, ?string $oldPath, string $folder): string
    {
        $this->delete($oldPath);
        return $this->upload($file, $folder);
    }

    /**
     * Generate URL publik dari path.
     *
     * @param string|null $path
     * @return string|null
     */
    public function url(?string $path): ?string
    {
        if (!$path) return null;
        return asset('storage/' . $path);
    }
}