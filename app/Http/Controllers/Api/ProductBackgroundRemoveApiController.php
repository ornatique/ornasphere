<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBackgroundRemove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductBackgroundRemoveApiController extends Controller
{
    private function resolveApiUser(Request $request)
    {
        $user = $request->user();

        if (!$user || empty($user->company_id)) {
            return null;
        }

        return $user;
    }

    private function unauthorizedResponse()
    {
        return response()->json([
            'success' => false,
            'code'    => 401,
            'message' => 'Unauthorized',
        ], 401);
    }

    private function canScopeByCreator(): bool
    {
        return Schema::hasColumn('product_background_removes', 'created_by');
    }

    private function scopedQueryForUser($user)
    {
        $query = ProductBackgroundRemove::query();

        if ($this->canScopeByCreator()) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $items = $this->scopedQueryForUser($user)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->formatItem($item));

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Products fetched successfully.',
            'data'    => $items,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $validator = Validator::make($request->all(), [
            'title'   => 'nullable|string|max:255',
            'image'   => 'required|array',
            'image.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $originalImages = [];
            $removedImages = [];

            foreach ($request->file('image') as $image) {
                $removedImages[]  = $this->removeBackground($image);
                $originalImages[] = $this->uploadOriginalImage($image);
            }

            $payload = [
                'title'          => $request->title,
                'original_image' => $originalImages,
                'removed_image'  => $removedImages,
                'status'         => 'completed',
                'error_message'  => null,
            ];

            if ($this->canScopeByCreator()) {
                $payload['created_by'] = $user->id;
            }

            $item = ProductBackgroundRemove::create($payload);

            return response()->json([
                'success' => true,
                'code'    => 201,
                'message' => 'Background removed successfully.',
                'data'    => $this->formatItem($item),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $item = $this->scopedQueryForUser($user)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'code' => 404,
                'message' => 'Product not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Product fetched successfully.',
            'data' => $this->formatItem($item),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $item = $this->scopedQueryForUser($user)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Product not found.',
                'data'    => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'   => 'nullable|string|max:255',
            'image'   => 'nullable|array',
            'image.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $originalImages = $item->original_image ?? [];
            $removedImages  = $item->removed_image ?? [];

            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $image) {
                    $removedImages[]  = $this->removeBackground($image);
                    $originalImages[] = $this->uploadOriginalImage($image);
                }
            }

            $item->update([
                'title'          => $request->title ?? $item->title,
                'original_image' => $originalImages,
                'removed_image'  => $removedImages,
                'status'         => count($originalImages) ? 'completed' : 'failed',
                'error_message'  => count($originalImages) ? null : 'No images available.',
            ]);

            return response()->json([
                'success' => true,
                'code'    => 200,
                'message' => 'Product updated successfully.',
                'data'    => $this->formatItem($item->fresh()),
            ], 200);

        } catch (\Exception $e) {
            $item->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => $e->getMessage(),
                'data'    => $this->formatItem($item->fresh()),
            ], 500);
        }
    }

    public function deleteImage(Request $request, $id)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $item = $this->scopedQueryForUser($user)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Product not found.',
                'data'    => null,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'remove_images'   => 'required|array',
            'remove_images.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $originalImages = $item->original_image ?? [];
        $removedImages  = $item->removed_image ?? [];

        foreach ($request->remove_images as $removeIndex) {
            $removeIndex = (int) $removeIndex;

            if (isset($originalImages[$removeIndex])) {
                $this->deleteFile($originalImages[$removeIndex]);
                unset($originalImages[$removeIndex]);
            }

            if (isset($removedImages[$removeIndex])) {
                $this->deleteFile($removedImages[$removeIndex]);
                unset($removedImages[$removeIndex]);
            }
        }

        $originalImages = array_values($originalImages);
        $removedImages  = array_values($removedImages);

        $item->update([
            'original_image' => $originalImages,
            'removed_image'  => $removedImages,
            'status'         => count($originalImages) ? 'completed' : 'failed',
            'error_message'  => count($originalImages) ? null : 'No images available.',
        ]);

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Image deleted successfully.',
            'data'    => $this->formatItem($item->fresh()),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->resolveApiUser($request);
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $item = $this->scopedQueryForUser($user)
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Product not found.',
            ], 404);
        }

        if (is_array($item->original_image)) {
            foreach ($item->original_image as $img) {
                $this->deleteFile($img);
            }
        } else {
            $this->deleteFile($item->original_image);
        }

        if (is_array($item->removed_image)) {
            foreach ($item->removed_image as $img) {
                $this->deleteFile($img);
            }
        } else {
            $this->deleteFile($item->removed_image);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'code'    => 200,
            'message' => 'Product deleted successfully.',
        ], 200);
    }

    private function formatItem($item)
    {
        $originalImages = collect($item->original_image ?? [])
            ->flatten()
            ->filter()
            ->values();

        $removedImages = collect($item->removed_image ?? [])
            ->flatten()
            ->filter()
            ->values();

        return [
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status,

            'original_image' => $originalImages
                ->map(fn ($img) => is_string($img) ? basename($img) : null)
                ->filter()
                ->values(),

            'original_image_url' => $originalImages
                ->map(fn ($img) => is_string($img) ? asset($this->normalizePublicUploadPath($img)) : null)
                ->filter()
                ->values(),

            'removed_image' => $removedImages
                ->map(fn ($img) => is_string($img) ? basename($img) : null)
                ->filter()
                ->values(),

            'removed_image_url' => $removedImages
                ->map(fn ($img) => is_string($img) ? asset($this->normalizePublicUploadPath($img)) : null)
                ->filter()
                ->values(),

            'error_message' => $item->error_message,

            'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function uploadOriginalImage($image)
    {
        $folder = public_path('uploads/bg-remove/original');

        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0777, true);
        }

        $fileName = time().'_'.uniqid().'.'.$image->getClientOriginalExtension();
        $image->move($folder, $fileName);

        return 'public/uploads/bg-remove/original/'.$fileName;
    }

    private function removeBackground($image)
    {
        $folder = public_path('uploads/bg-remove/removed');

        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0777, true);
        }

        $fileName = time().'_'.uniqid().'.png';

        $savePath = $folder.'/'.$fileName;

        // LOCAL PYTHON API
        $response = Http::timeout(300)
            ->attach(
                'image',
                fopen($image->getRealPath(), 'r'),
                $image->getClientOriginalName()
            )
            ->post('https://passing-wrongly-wick.ngrok-free.dev/remove-bg');

        if ($response->successful()) {

            file_put_contents($savePath, $response->body());

            return 'public/uploads/bg-remove/removed/'.$fileName;
        }

        throw new \Exception(
            'Background remove failed. Status: '
            . $response->status()
            . ' Body: '
            . $response->body()
        );
    }

    private function deleteFile($path)
    {
        $normalized = $this->normalizeDiskPath($path);

        if ($normalized && File::exists(public_path($normalized))) {
            File::delete(public_path($normalized));
        }
    }

    private function normalizePublicUploadPath(string $path): string
    {
        $trimmed = ltrim($path, '/');
        if (str_starts_with($trimmed, 'public/')) {
            return $trimmed;
        }
        if (str_starts_with($trimmed, 'uploads/')) {
            return 'public/' . $trimmed;
        }
        return $trimmed;
    }

    private function normalizeDiskPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $trimmed = ltrim($path, '/');
        if (str_starts_with($trimmed, 'public/uploads/')) {
            return substr($trimmed, strlen('public/'));
        }

        return $trimmed;
    }
}
