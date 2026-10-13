<?php

namespace App\Jobs;

use App\Models\StockGalleryMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessStockGalleryImage implements ShouldQueue
{
    use Queueable;

    public $timeout = 180;
    public $tries = 1;

    public function __construct(public int $mediaId, public string $revision)
    {
        $this->onConnection(config('stock_gallery.queue_connection'));
        $this->onQueue(config('stock_gallery.queue'));
    }

    public function handle(): void
    {
        \App\Support\StockGalleryLog::write('worker.started', ['media_id' => $this->mediaId, 'revision' => $this->revision]);
        $media = StockGalleryMedia::whereKey($this->mediaId)->where('revision', $this->revision)->first();
        if (!$media) {
            \App\Support\StockGalleryLog::write('worker.skipped_stale_revision', ['media_id' => $this->mediaId, 'revision' => $this->revision]);
            return;
        }
        $media->update(['status' => 'processing', 'error' => null]);
        $path = 'stock-gallery/' . $media->company_id . '/' . $this->revision . '.png';
        $disk = Storage::disk('local');
        try {
            if (!is_file(config('stock_gallery.python'))) throw new \RuntimeException('Configured background-removal Python executable is missing. Set STOCK_GALLERY_PYTHON on this server.');
            \App\Support\StockGalleryLog::write('worker.python_started', ['media_id' => $this->mediaId, 'revision' => $this->revision]);
            $process = new Process([
                config('stock_gallery.python'), base_path('scripts/stock-gallery/remove_background.py'),
                $disk->path($media->original_path), $disk->path($path),
            ]);
            $process->setTimeout(160)->mustRun();
            if (!$disk->exists($path) || @getimagesize($disk->path($path))[2] !== IMAGETYPE_PNG) {
                throw new \RuntimeException('Invalid cutout output.');
            }
            $updated = StockGalleryMedia::whereKey($this->mediaId)->where('revision', $this->revision)
                ->update(['status' => 'ready', 'cutout_path' => $path, 'error' => null]);
            if (!$updated) $disk->delete($path);
            \App\Support\StockGalleryLog::write($updated ? 'worker.ready' : 'worker.output_discarded', ['media_id' => $this->mediaId, 'revision' => $this->revision]);
        } catch (\Throwable $e) {
            $disk->delete($path);
            report($e);
            $this->failed($e);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        \App\Support\StockGalleryLog::write('worker.failed', ['media_id' => $this->mediaId, 'revision' => $this->revision,
            'exception' => $exception ? get_class($exception) : null,
            'detail' => $exception ? mb_substr($exception->getMessage(), 0, 2500) : 'Worker terminated']);
        StockGalleryMedia::whereKey($this->mediaId)->where('revision', $this->revision)->update([
            'status' => 'failed', 'enabled' => false,
            'error' => 'Background removal failed. Retry with a clear photo, or ask your administrator to check the image worker.',
        ]);
    }
}
