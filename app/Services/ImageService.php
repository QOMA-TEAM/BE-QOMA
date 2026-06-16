<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    private string $disk;
    private ?string $publicUrl;

    public function __construct()
    {
        $this->disk      = config('filesystems.default', 'supabase');
        $this->publicUrl = env('SUPABASE_URL') . '/storage/v1/object/public/' . env('SUPABASE_STORAGE_BUCKET');
    }

    /**
     * Upload gambar ke Supabase Storage
     * Return: path relatif untuk disimpan di DB
     * Contoh return: "menu/usaha-id/uuid.jpg"
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $folder . '/' . $filename;

        Storage::disk($this->disk)->put($path, file_get_contents($file), 'public');

        return $path;
    }

    /**
     * Hapus gambar dari Supabase Storage
     */
    public function delete(?string $path): void
    {
        if (!$path) return;

        try {
            Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            \Log::warning("ImageService delete gagal: {$path} — " . $e->getMessage());
        }
    }

    /**
     * Ganti gambar lama dengan yang baru
     */
    public function replace(UploadedFile $file, ?string $oldPath, string $folder): string
    {
        $this->delete($oldPath);
        return $this->upload($file, $folder);
    }

    /**
     * Generate URL publik dari path
     * Contoh: "menu/usaha-id/uuid.jpg"
     * → "https://xxxx.supabase.co/storage/v1/object/public/qoma-storage/menu/usaha-id/uuid.jpg"
     */
    public function url(?string $path): ?string
    {
        if (!$path) return null;

        // Kalau masih pakai disk supabase
        if ($this->disk === 'supabase') {
            return $this->publicUrl . '/' . ltrim($path, '/');
        }

        // Fallback ke local (development tanpa Supabase)
        return asset('storage/' . $path);
    }
}