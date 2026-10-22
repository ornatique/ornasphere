<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStockGalleryImage;
use App\Models\Company;
use App\Models\ItemSet;
use App\Models\StockGalleryMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ItemSetImageService;

class StockGalleryMediaController extends Controller
{
    private function requireInput(bool $condition, string $message): void
    {
        if (!$condition) throw \Illuminate\Validation\ValidationException::withMessages(['media' => $message]);
    }

    private function item(Request $request, string $slug, string $encryptedId): ItemSet
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        abort_unless(\App\Services\BranchReadOnly::canWrite($request->user(), $company->id) || ($request->isMethod('GET') && \App\Services\CompanyBranches::isActive($company) && \App\Services\CompanyBranches::visibleCompanies($request->user())->contains('id', $company->id)), 403);
        try { $id = Crypt::decryptString($encryptedId); }
        catch (\Throwable) { abort(404); }
        return ItemSet::with('item')->where('company_id', $company->id)->where('is_final', 1)->findOrFail($id);
    }

    public function show(Request $request, string $slug, string $encryptedId)
    {
        $item = $this->item($request, $slug, $encryptedId);
        $media = StockGalleryMedia::where('item_set_id', $item->id)->first();
        $fresh = $media && $media->matchesSource($item);
        $asset = fn ($kind) => route('company.stock-gallery.media.asset', [$slug, $encryptedId, $kind]);
        return response()->json([
            'category' => $item->item->try_on_category ?: 'none',
            'revision' => $media?->revision,
            'status' => $fresh ? $media->status : ($item->image_path ? 'unprocessed' : 'empty'),
            'error' => $fresh ? $media->error : null,
            'original_url' => $item->image_path ? $asset('original') : null,
            'cutout_url' => $fresh && $media->status === 'ready' ? $asset('cutout') . '?v=' . $media->revision : null,
            'approved' => $fresh && $media->approved,
            'enabled' => $fresh && $media->enabled && $media->approved && $item->item->try_on_category !== 'none',
            'settings' => array_merge(StockGalleryMedia::defaults(), $media->settings ?? []),
            'label' => $item->qr_code ?: $item->barcode,
        ])->header('Cache-Control', 'no-store');
    }

    public function upload(Request $request, string $slug, string $encryptedId)
    {
        $item = $this->item($request, $slug, $encryptedId);
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:' . ItemSetImageService::MAX_UPLOAD_KB . '|dimensions:max_width=8000,max_height=8000']);
        $file = $request->file('image');
        $disk = $item->image_disk ?: config('filesystems.item_image_disk', 'public');
        $path = $file->store('item-images/company-' . $item->company_id, $disk);
        abort_unless($path, 503, 'Image storage is unavailable.');
        // Keep the previous image file: other exports or previews may still reference it.
        $item->update(['image_disk' => $disk, 'image_path' => $path, 'image_mime' => $file->getMimeType(),
            'image_size' => $file->getSize(), 'image_uploaded_at' => now()]);
        return $this->process($request, $slug, $encryptedId);
    }

    public function process(Request $request, string $slug, string $encryptedId)
    {
        $item = $this->item($request, $slug, $encryptedId);
        $this->requireInput((bool) $item->image_path, 'Upload an image first.');
        \App\Support\StockGalleryLog::write('processing.requested', ['item_set_id' => $item->id, 'company_id' => $item->company_id]);
        $sourceDisk = $item->image_disk ?: config('filesystems.item_image_disk', 'public');
        $source = Storage::disk($sourceDisk);
        $this->requireInput($source->exists($item->image_path), 'Original image is missing. Upload it again.');
        $this->requireInput($source->size($item->image_path) <= 15 * 1024 * 1024, 'Use an image 15 MB or smaller.');
        $revision = (string) Str::uuid();
        $path = 'stock-gallery/' . $item->company_id . '/' . $revision . '-original';
        $stream = $source->readStream($item->image_path);
        try { $saved = Storage::disk('local')->put($path, $stream); }
        finally { if (is_resource($stream)) fclose($stream); }
        abort_unless($saved, 503, 'Image storage is unavailable.');
        $media = DB::transaction(function () use ($item, $sourceDisk, $path, $revision) {
            $media = StockGalleryMedia::firstOrCreate(['item_set_id' => $item->id], ['company_id' => $item->company_id]);
            $media->update(['source_disk' => $sourceDisk, 'source_path' => $item->image_path, 'original_path' => $path,
                'cutout_path' => null, 'revision' => $revision, 'status' => 'queued', 'error' => null,
                'approved' => false, 'enabled' => false]);
            return $media;
        });
        try {
            ProcessStockGalleryImage::dispatch($media->id, $revision);
            \App\Support\StockGalleryLog::write('processing.dispatched', ['media_id' => $media->id, 'revision' => $revision,
                'connection' => config('stock_gallery.queue_connection'), 'queue' => config('stock_gallery.queue')]);
        }
        catch (\Throwable $e) {
            report($e);
            \App\Support\StockGalleryLog::write('processing.dispatch_failed', ['media_id' => $media->id, 'revision' => $revision, 'exception' => get_class($e)]);
            $media->update(['status' => 'failed', 'error' => 'Image worker could not be started. Please retry.']);
        }
        return $this->show($request, $slug, $encryptedId);
    }

    public function update(Request $request, string $slug, string $encryptedId)
    {
        $item = $this->item($request, $slug, $encryptedId);
        $data = $request->validate([
            'approved' => 'required|boolean', 'enabled' => 'required|boolean', 'revision' => 'required|string',
            'scale' => 'required|numeric|between:0.1,4', 'x' => 'required|numeric|between:-1,1',
            'y' => 'required|numeric|between:-1,1', 'rotation' => 'required|numeric|between:-180,180',
            'finger' => 'required|in:thumb,index,middle,ring,little',
            'earring_mode' => 'required|in:single,pair',
        ]);
        $media = StockGalleryMedia::where('item_set_id', $item->id)->firstOrFail();
        $this->requireInput($media->matchesSource($item) && $media->status === 'ready', 'Process the current image first.');
        $this->requireInput(!$data['enabled'] || ($data['approved'] && $item->item->try_on_category !== 'none'),
            'Approve the cutout and select a Try-On Category in Item Master before enabling.');
        $this->requireInput(hash_equals((string) $media->revision, $data['revision']), 'The image changed. Reload its preview before saving.');
        $media->update(['approved' => $data['approved'], 'enabled' => $data['enabled'],
            'settings' => collect($data)->except(['approved', 'enabled', 'revision'])->all()]);
        return $this->show($request, $slug, $encryptedId);
    }

    public function asset(Request $request, string $slug, string $encryptedId, string $kind)
    {
        $item = $this->item($request, $slug, $encryptedId);
        if ($kind === 'original') {
            $disk = Storage::disk($item->image_disk ?: config('filesystems.item_image_disk', 'public'));
            $path = $item->image_path;
        } else {
            abort_unless($kind === 'cutout', 404);
            $media = StockGalleryMedia::where('item_set_id', $item->id)->firstOrFail();
            abort_unless($media->matchesSource($item) && $media->status === 'ready', 404);
            $disk = Storage::disk('local');
            $path = $media->cutout_path;
        }
        abort_unless($path && $disk->exists($path), 404);
        return $disk->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
