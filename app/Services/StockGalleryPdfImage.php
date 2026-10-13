<?php

namespace App\Services;

use App\Models\ItemSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StockGalleryPdfImage
{
    public function dataUri(ItemSet $item): ?string
    {
        if (!$item->image_path) return null;
        $disk = $item->image_disk ?: config('filesystems.item_image_disk', 'public');
        $key = 'stock-pdf-v1-'.hash('sha256', json_encode([$item->company_id, $item->id, $disk, $item->image_path, $item->image_mime, (string) $item->updated_at]));
        try {
            return Cache::store('file')->remember($key, now()->addDays(7), function () use ($item, $disk) {
                // One storage read rather than an existence request followed by a read.
                $bytes = Storage::disk($disk)->get($item->image_path);
                if (!$bytes) return null;
                if (!function_exists('imagecreatefromstring')) {
                    return 'data:'.($item->image_mime ?: 'image/jpeg').';base64,'.base64_encode($bytes);
                }
                $source = @imagecreatefromstring($bytes);
                if (!$source) return null;
                $thumb = null;
                try {
                    $scale = min(1, 700 / max(imagesx($source), imagesy($source)));
                    $width = max(1, (int) round(imagesx($source) * $scale));
                    $height = max(1, (int) round(imagesy($source) * $scale));
                    $thumb = imagecreatetruecolor($width, $height);
                    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
                    ob_start();
                    try {
                        imagejpeg($thumb, null, 82);
                        $jpeg = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                    return 'data:image/jpeg;base64,'.base64_encode($jpeg);
                } finally {
                    imagedestroy($source);
                    if ($thumb) imagedestroy($thumb);
                }
            });
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }
}
