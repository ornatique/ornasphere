<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ItemSet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StockGalleryApiController extends Controller
{
    public function index(Request $request)
    {
        $company = $this->company($request);
        $perPage = min(max((int) $request->input('per_page', 24), 1), 100);

        $rows = $this->galleryQuery($company, $request)
            ->latest('item_sets.id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (ItemSet $itemSet) => $this->payload($itemSet))->values(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $company = $this->company($request);
        $selectedIds = $this->selectedIds($request);
        $matchedItemCount = (clone $this->selectedOrFilteredQuery($company, $request))->count();
        $itemSets = $this->selectedOrFilteredQuery($company, $request)
            ->whereNotNull('item_sets.image_path')
            ->latest('item_sets.id')
            ->limit(300)
            ->get()
            ->map(function (ItemSet $itemSet) {
                $itemSet->stock_gallery_payload = $this->payload($itemSet);
                $itemSet->stock_gallery_image = $this->imageDataUri($itemSet);

                return $itemSet;
            })
            ->filter(fn (ItemSet $itemSet) => !empty($itemSet->stock_gallery_image))
            ->values();
        $emptyMessage = $matchedItemCount > 0
            ? 'Items are available in stock, but image is not uploaded for the selected items.'
            : 'No stock gallery items found.';

        if (!$request->boolean('direct')) {
            return response()->json([
                'success' => true,
                'message' => $itemSets->isEmpty() ? $emptyMessage : 'Stock gallery PDF is ready.',
                'count' => $itemSets->count(),
                'pdf_url' => $this->pdfUrl($request, $selectedIds),
                'format' => 'pdf',
            ]);
        }

        return Pdf::loadView('company.stock_gallery.pdf', compact('company', 'itemSets', 'emptyMessage'))
            ->setPaper('a4', 'portrait')
            ->download('stock-gallery-' . now()->format('Ymd-His') . '.pdf');
    }

    public function imageLinks(Request $request)
    {
        $company = $this->company($request);

        $items = $this->selectedOrFilteredQuery($company, $request)
            ->whereNotNull('item_sets.image_path')
            ->latest('item_sets.id')
            ->limit(300)
            ->get()
            ->filter(fn (ItemSet $itemSet) => $this->imageExists($itemSet))
            ->map(fn (ItemSet $itemSet) => [
                'id' => (int) $itemSet->id,
                'label_code' => (string) ($itemSet->qr_code ?: $itemSet->barcode ?: $itemSet->id),
                'image_url' => $this->itemImageUrl($itemSet),
                'download_url' => url('/api/stock-gallery/' . $itemSet->id . '/download-image'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function downloadImage(Request $request, $id)
    {
        $company = $this->company($request);

        $itemSet = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        abort_unless($itemSet->image_path, 404, 'Image not uploaded.');

        $disk = Storage::disk($itemSet->image_disk ?: $this->itemImageDisk());
        abort_unless($disk->exists($itemSet->image_path), 404, 'Image file not found.');

        return $disk->download($itemSet->image_path, $this->imageFilename($itemSet));
    }

    private function selectedOrFilteredQuery(Company $company, Request $request): Builder
    {
        $query = $this->galleryQuery($company, $request);
        $ids = $this->selectedIds($request);

        if ($request->input('scope') === 'selected' && empty($ids)) {
            abort(422, 'Please select at least one item.');
        }

        if (!empty($ids)) {
            $query->whereIn('item_sets.id', $ids);
        }

        return $query;
    }

    private function galleryQuery(Company $company, Request $request): Builder
    {
        $query = ItemSet::query()
            ->with('item')
            ->where('item_sets.company_id', $company->id)
            ->where('item_sets.is_final', 1)
            ->whereNotNull('item_sets.qr_code');

        if ($request->filled('item_id')) {
            $query->where('item_sets.item_id', $request->input('item_id'));
        }

        if ($request->input('stock_status') === 'in') {
            $query->where('item_sets.is_sold', 0);
        } elseif ($request->input('stock_status') === 'out') {
            $query->where('item_sets.is_sold', 1);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('item_sets.qr_code', 'like', "%{$search}%")
                    ->orWhere('item_sets.barcode', 'like', "%{$search}%")
                    ->orWhere('item_sets.HUID', 'like', "%{$search}%")
                    ->orWhereHas('item', function (Builder $itemQuery) use ($search) {
                        $itemQuery->where('item_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function payload(ItemSet $itemSet): array
    {
        return [
            'id' => (int) $itemSet->id,
            'item_name' => optional($itemSet->item)->item_name ?? '-',
            'label_code' => $itemSet->qr_code ?: $itemSet->barcode ?: '-',
            'qr_code' => $itemSet->qr_code,
            'huid' => $itemSet->HUID ?: '-',
            'gross_weight' => number_format((float) $itemSet->gross_weight, 3, '.', ''),
            'other_weight' => number_format((float) $itemSet->other, 3, '.', ''),
            'net_weight' => number_format((float) $itemSet->net_weight, 3, '.', ''),
            'has_other_weight' => (float) $itemSet->other > 0,
            'stock_status' => ((int) $itemSet->is_sold === 1) ? 'Out Stock' : 'In Stock',
            'is_sold' => (int) $itemSet->is_sold,
            'image_url' => $this->itemImageUrl($itemSet),
            'has_image' => !empty($itemSet->image_path),
            'image_download_url' => url('/api/stock-gallery/' . $itemSet->id . '/download-image'),
        ];
    }

    private function selectedIds(Request $request): array
    {
        $ids = $request->input('selected_ids', []);

        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        if (!is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function company(Request $request): Company
    {
        return Company::findOrFail($request->user()->company_id);
    }

    private function itemImageDisk(): string
    {
        return (string) config('filesystems.item_image_disk', 'public');
    }

    private function itemImageUrl(?ItemSet $itemSet): ?string
    {
        if (!$itemSet || !$itemSet->image_path) {
            return null;
        }

        return Storage::disk($itemSet->image_disk ?: $this->itemImageDisk())->url($itemSet->image_path);
    }

    private function imageDataUri(ItemSet $itemSet): ?string
    {
        if (!$itemSet->image_path) {
            return null;
        }

        try {
            $disk = Storage::disk($itemSet->image_disk ?: $this->itemImageDisk());
            if (!$disk->exists($itemSet->image_path)) {
                return null;
            }

            $mime = $itemSet->image_mime ?: 'image/jpeg';

            return 'data:' . $mime . ';base64,' . base64_encode($disk->get($itemSet->image_path));
        } catch (\Throwable) {
            return null;
        }
    }

    private function imageExists(ItemSet $itemSet): bool
    {
        if (!$itemSet->image_path) {
            return false;
        }

        try {
            return Storage::disk($itemSet->image_disk ?: $this->itemImageDisk())->exists($itemSet->image_path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function imageFilename(ItemSet $itemSet): string
    {
        $extension = pathinfo($itemSet->image_path ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $label = $itemSet->qr_code ?: $itemSet->barcode ?: ('item-' . $itemSet->id);

        return Str::slug($label) . '.' . $extension;
    }

    private function pdfUrl(Request $request, array $selectedIds): string
    {
        $query = [
            'direct' => 1,
            'scope' => $request->input('scope', $selectedIds ? 'selected' : 'filtered'),
            'item_id' => $request->input('item_id'),
            'stock_status' => $request->input('stock_status'),
            'search' => $request->input('search'),
        ];

        if ($selectedIds) {
            $query['selected_ids'] = implode(',', $selectedIds);
        }

        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return url('/api/stock-gallery/pdf') . '?' . http_build_query($query);
    }
}
