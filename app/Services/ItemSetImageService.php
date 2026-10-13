<?php

namespace App\Services;

use App\Jobs\ProcessItemSetImageUpload;
use App\Models\ItemSet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemSetImageService
{
    public const MAX_UPLOAD_KB = 15360;

    public function itemImageDisk(): string
    {
        return (string) config('filesystems.item_image_disk', 'public');
    }

    public function imageUrl(?ItemSet $itemSet): ?string
    {
        if (!$itemSet || !$itemSet->image_path) {
            return null;
        }

        return Storage::disk($itemSet->image_disk ?: $this->itemImageDisk())->url($itemSet->image_path);
    }

    public function queueUpload(ItemSet $itemSet, UploadedFile $file): void
    {
        if ((string) config('item_images.processing', 'sync') === 'sync') {
            $pendingPath = sprintf(
                'item-image-pending/company-%d/%d/%s.%s',
                $itemSet->company_id,
                $itemSet->id,
                (string) Str::uuid(),
                strtolower($file->getClientOriginalExtension() ?: 'jpg')
            );

            $sourcePath = Storage::disk('local')->path($pendingPath);
            File::ensureDirectoryExists(dirname($sourcePath));
            copy($file->getRealPath(), $sourcePath);

            $this->processPendingUpload(
                (int) $itemSet->id,
                (int) $itemSet->company_id,
                $pendingPath,
                $file->getMimeType() ?: 'image/jpeg'
            );

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $pendingPath = sprintf(
            'item-image-pending/company-%d/%d/%s.%s',
            $itemSet->company_id,
            $itemSet->id,
            (string) Str::uuid(),
            $extension
        );

        $stream = fopen($file->getRealPath(), 'r');
        Storage::disk('local')->put($pendingPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        ProcessItemSetImageUpload::dispatch(
            (int) $itemSet->id,
            (int) $itemSet->company_id,
            $pendingPath,
            $file->getMimeType() ?: 'image/jpeg'
        );
    }

    public function processPendingUpload(int $itemSetId, int $companyId, string $pendingPath, string $originalMime): void
    {
        $itemSet = ItemSet::query()
            ->where('company_id', $companyId)
            ->findOrFail($itemSetId);

        $pendingDisk = Storage::disk('local');
        $sourcePath = $pendingDisk->path($pendingPath);
        $prepared = null;

        try {
            $prepared = $this->prepareMediumImage($sourcePath, $originalMime);
            $disk = $this->itemImageDisk();
            $path = sprintf(
                'item-images/company-%d/%s.%s',
                $itemSet->company_id,
                (string) Str::uuid(),
                $prepared['extension']
            );

            $stream = fopen($prepared['path'], 'r');
            Storage::disk($disk)->put($path, $stream, [
                'ContentType' => $prepared['mime'],
            ]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $oldDisk = $itemSet->image_disk;
            $oldPath = $itemSet->image_path;

            $itemSet->update([
                'image_disk' => $disk,
                'image_path' => $path,
                'image_mime' => $prepared['mime'],
                'image_size' => Storage::disk($disk)->size($path),
                'image_uploaded_at' => now(),
            ]);

            if ($oldPath) {
                Storage::disk($oldDisk ?: $disk)->delete($oldPath);
            }
        } finally {
            $pendingDisk->delete($pendingPath);

            if (!empty($prepared['cleanup'])) {
                @unlink($prepared['path']);
            }
        }
    }

    private function prepareMediumImage(string $sourcePath, string $originalMime): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return [
                'path' => $sourcePath,
                'mime' => $originalMime,
                'extension' => match ($originalMime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                },
                'cleanup' => false,
            ];
        }

        $contents = file_get_contents($sourcePath);
        $image = $contents ? @imagecreatefromstring($contents) : false;

        if (!$image) {
            return [
                'path' => $sourcePath,
                'mime' => $originalMime,
                'extension' => 'jpg',
                'cleanup' => false,
            ];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxSide = 1200;
        $ratio = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $tempPath = tempnam(sys_get_temp_dir(), 'item-image-');
        $mime = 'image/jpeg';
        $extension = 'jpg';

        if (function_exists('imagewebp') && imagewebp($canvas, $tempPath, 82)) {
            $mime = 'image/webp';
            $extension = 'webp';
        } else {
            imagejpeg($canvas, $tempPath, 84);
        }

        imagedestroy($image);
        imagedestroy($canvas);

        return [
            'path' => $tempPath,
            'mime' => $mime,
            'extension' => $extension,
            'cleanup' => true,
        ];
    }
}
